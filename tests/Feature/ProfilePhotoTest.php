<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Profile photo upload
|--------------------------------------------------------------------------
|
| Pehle poora flow toota hua tha: `public/storage` link nahi tha (saare
| avatar 404), student photo sirf `students` table me likhi jati thi (admin
| ka list `users.profile_image` padhta hai, isliye wahan kabhi nahi dikhti
| thi), aur badli hui photo ka file kabhi delete nahi hota tha.
|
*/

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const SVG = 'data:image/svg+xml;base64,PHN2Zy8+';

    private function makeStudent(): User
    {
        $user = User::create([
            'name' => 'Karan',
            'email' => 'karan@example.com',
            'password' => Hash::make('secret1234'),
            'role' => 'student',
        ]);

        Student::create(['user_id' => $user->id, 'college' => 'MIT']);

        return $user;
    }

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Anish',
            'email' => 'anish@example.com',
            'password' => Hash::make('secret1234'),
            'role' => 'admin',
        ]);
    }

    public function test_it_stores_an_uploaded_photo_and_returns_a_reachable_url(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->makeStudent());

        $response = $this->postJson('/api/auth/upload-image', [
            'image' => 'data:image/png;base64,'.self::PNG,
        ]);

        $response->assertOk();

        $url = $response->json('url');
        $this->assertStringContainsString('avatars/', $url);
        Storage::disk('public')->assertExists('avatars/'.basename(parse_url($url, PHP_URL_PATH)));
    }

    public function test_student_photo_is_saved_to_both_students_and_users_tables(): void
    {
        Storage::fake('public');
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $upload = $this->postJson('/api/auth/upload-image', [
            'image' => 'data:image/png;base64,'.self::PNG,
        ])->assertOk();

        $url = $upload->json('url');

        $this->putJson('/api/student/profile', ['profile_image' => $url])->assertOk();

        // `users` row bhi update honi chahiye — admin ka student list yahi padhta hai.
        $this->assertSame($url, $user->fresh()->profile_image);
        $this->assertSame($url, Student::where('user_id', $user->id)->value('profile_image'));
    }

    public function test_replacing_a_photo_deletes_the_old_file_but_keeps_the_new_one(): void
    {
        Storage::fake('public');
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        // Real flow: photo upload hoti hai aur turant save bhi ho jaati hai.
        $first = $this->postJson('/api/auth/upload-image', [
            'image' => 'data:image/png;base64,'.self::PNG,
        ])->json('url');

        $firstPath = 'avatars/'.basename(parse_url($first, PHP_URL_PATH));
        $this->putJson('/api/student/profile', ['profile_image' => $first])->assertOk();
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->postJson('/api/auth/upload-image', [
            'image' => 'data:image/png;base64,'.self::PNG,
        ])->json('url');

        $secondPath = 'avatars/'.basename(parse_url($second, PHP_URL_PATH));
        $this->putJson('/api/student/profile', ['profile_image' => $second])->assertOk();

        // Purani file saaf, nayi file bachi hui.
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertSame($second, $user->fresh()->profile_image);
    }

    public function test_photo_is_not_deleted_when_saved_again_without_changing(): void
    {
        Storage::fake('public');
        $user = $this->makeStudent();
        Sanctum::actingAs($user);

        $url = $this->postJson('/api/auth/upload-image', [
            'image' => 'data:image/png;base64,'.self::PNG,
        ])->json('url');

        $path = 'avatars/'.basename(parse_url($url, PHP_URL_PATH));
        $this->putJson('/api/student/profile', ['profile_image' => $url])->assertOk();
        $this->putJson('/api/student/profile', ['profile_image' => $url])->assertOk();

        Storage::disk('public')->assertExists($path);
    }

    public function test_admin_photo_is_saved_and_old_file_is_deleted(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $old = 'http://localhost:8000/storage/avatars/old-admin.png';
        Storage::disk('public')->put('avatars/old-admin.png', 'x');
        $admin->forceFill(['profile_image' => $old])->save();

        $url = $this->postJson('/api/auth/upload-image', [
            'image' => 'data:image/png;base64,'.self::PNG,
        ])->json('url');

        $this->putJson('/api/admin/profile', ['profile_image' => $url])->assertOk();

        $this->assertSame($url, $admin->fresh()->profile_image);
        Storage::disk('public')->assertMissing('avatars/old-admin.png');
    }

    public function test_it_rejects_a_file_that_only_claims_to_be_an_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->makeStudent());

        // SVG me <script> chala sakta hai — byte-level check use reject karta hai.
        $this->postJson('/api/auth/upload-image', ['image' => self::SVG])
            ->assertStatus(422)
            ->assertJsonPath('message', 'That file is not a valid image. Use JPG, PNG, GIF or WEBP.');

        // PHP payload ko `data:image/png` bol kar bhejna — bytes dekh kar pakda jana chahiye.
        $this->postJson('/api/auth/upload-image', [
            'image' => 'data:image/png;base64,'.base64_encode('<?php system($_GET[0]); ?>'),
        ])->assertStatus(422);

        // `data:image/` prefix hone se hi koi farak nahi padta.
        $this->postJson('/api/auth/upload-image', [
            'image' => 'data:image/png;base64,'.base64_encode('not an image at all'),
        ])->assertStatus(422);

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_upload_requires_authentication(): void
    {
        Storage::fake('public');

        $this->postJson('/api/auth/upload-image', ['image' => 'data:image/png;base64,'.self::PNG])
            ->assertStatus(401);
    }
}

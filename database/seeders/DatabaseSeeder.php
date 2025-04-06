<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoMetric;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        // Create regular users
        $user1 = User::create([
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $user2 = User::create([
            'name' => 'Jane Smith',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        // Create categories
        $entertainment = Category::create([
            'name' => 'Entertainment',
            'description' => 'Entertainment videos',
        ]);

        $education = Category::create([
            'name' => 'Education',
            'description' => 'Educational content',
        ]);

        $sports = Category::create([
            'name' => 'Sports',
            'description' => 'Sports and fitness videos',
        ]);

        // Create videos
        $video1 = Video::create([
            'title' => 'Introduction to Laravel',
            'description' => 'Learn the basics of Laravel framework',
            'user_id' => $user1->id,
            'file_path' => 'videos/laravel_intro.mp4',
            'thumbnail_path' => 'thumbnails/laravel_intro.jpg',
            'status' => 'complete',
            'view_count' => 125,
        ]);

        $video2 = Video::create([
            'title' => 'Advanced JavaScript Techniques',
            'description' => 'Dive deep into advanced JavaScript concepts',
            'user_id' => $user2->id,
            'file_path' => 'videos/advanced_js.mp4',
            'thumbnail_path' => 'thumbnails/advanced_js.jpg',
            'status' => 'complete',
            'view_count' => 85,
        ]);

        $video3 = Video::create([
            'title' => 'Workout Routine for Beginners',
            'description' => 'Simple workout routine for beginners',
            'user_id' => $admin->id,
            'file_path' => 'videos/workout.mp4',
            'thumbnail_path' => 'thumbnails/workout.jpg',
            'status' => 'complete',
            'view_count' => 250,
        ]);

        $video4 = Video::create([
            'title' => 'Behind the Scenes: Movie Production',
            'description' => 'Get an exclusive look at how movies are made',
            'user_id' => $user1->id,
            'file_path' => 'videos/movie_production.mp4',
            'thumbnail_path' => 'thumbnails/movie_production.jpg',
            'status' => 'complete',
            'view_count' => 320,
        ]);

        $video5 = Video::create([
            'title' => 'Building a RESTful API',
            'description' => 'Learn how to build a RESTful API with Laravel',
            'user_id' => $user2->id,
            'file_path' => 'videos/restful_api.mp4',
            'thumbnail_path' => 'thumbnails/restful_api.jpg',
            'status' => 'complete',
            'view_count' => 180,
        ]);

        // Attach categories to videos
        $video1->categories()->attach([$education->id]);
        $video2->categories()->attach([$education->id]);
        $video3->categories()->attach([$sports->id]);
        $video4->categories()->attach([$entertainment->id]);
        $video5->categories()->attach([$education->id]);

        // Create video metrics
        foreach ([$video1, $video2, $video3, $video4, $video5] as $video) {
            VideoMetric::create([
                'video_id' => $video->id,
                'views' => $video->view_count,
                'likes' => rand(10, 100),
                'dislikes' => rand(1, 20),
                'comments_count' => rand(5, 25),
            ]);
        }

        // Create comments
        Comment::create([
            'user_id' => $user1->id,
            'video_id' => $video2->id,
            'content' => 'Great content! I learned a lot from this video.',
            'is_approved' => true,
        ]);

        Comment::create([
            'user_id' => $user2->id,
            'video_id' => $video1->id,
            'content' => 'This helped me understand Laravel better. Thanks!',
            'is_approved' => true,
        ]);

        Comment::create([
            'user_id' => $admin->id,
            'video_id' => $video3->id,
            'content' => 'Simple but effective workout routine.',
            'is_approved' => true,
        ]);

        Comment::create([
            'user_id' => $user2->id,
            'video_id' => $video4->id,
            'content' => 'Fascinating behind-the-scenes look!',
            'is_approved' => true,
        ]);

        Comment::create([
            'user_id' => $user1->id,
            'video_id' => $video5->id,
            'content' => 'This video really helped me understand RESTful APIs.',
            'is_approved' => true,
        ]);
    }
}

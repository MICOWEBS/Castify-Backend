<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $featuredVideos = Video::where('status', 'complete')
            ->where('featured', true)
            ->latest()
            ->take(5)
            ->get();
            
        $recentVideos = Video::where('status', 'complete')
            ->latest()
            ->take(10)
            ->get();
            
        $categories = Category::with(['videos' => function($query) {
            $query->where('status', 'complete')->take(8);
        }])
        ->take(4)
        ->get();
        
        return view('dashboard', compact('featuredVideos', 'recentVideos', 'categories'));
    }
    
    /**
     * Show the browse page.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function browse()
    {
        $categories = Category::with(['videos' => function($query) {
            $query->where('status', 'complete');
        }])
        ->get();
        
        return view('browse', compact('categories'));
    }
    
    /**
     * Show the user profile page.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function profile()
    {
        $user = Auth::user();
        $watchHistory = $user->watchHistory()
            ->with('video')
            ->latest()
            ->take(10)
            ->get();
            
        $profiles = $user->profiles;
        
        return view('profile', compact('user', 'watchHistory', 'profiles'));
    }
} 
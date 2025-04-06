<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="StoreVideoRequest",
 *     title="Store Video Request",
 *     description="Request body for creating a new video",
 *     required={"title"},
 *     @OA\Property(property="title", type="string", maxLength=100, example="Introduction to Laravel"),
 *     @OA\Property(property="description", type="string", maxLength=1000, example="Learn the basics of Laravel framework"),
 *     @OA\Property(property="url", type="string", format="url", example="https://example.com/videos/laravel-intro.mp4"),
 *     @OA\Property(property="thumbnail", type="string", format="url", example="https://example.com/thumbnails/laravel-intro.jpg"),
 *     @OA\Property(
 *         property="category_ids",
 *         type="array", 
 *         @OA\Items(type="integer", example=1)
 *     ),
 *     @OA\Property(property="video", type="string", format="binary", description="Video file to upload"),
 *     @OA\Property(property="thumbnail_file", type="string", format="binary", description="Thumbnail image to upload")
 * )
 */
class StoreVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users can upload videos
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'url' => 'nullable|url|max:255',
            'thumbnail' => 'nullable|url|max:255',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'video' => 'nullable|file|mimes:mp4,avi,mov|max:102400',
            'thumbnail_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A title is required',
            'video.required' => 'You must upload a video file',
            'video.mimetypes' => 'The file must be a video (MP4, AVI, MPEG, MOV)',
            'video.max' => 'The video may not be larger than 100MB',
            'thumbnail.image' => 'The thumbnail must be an image',
            'thumbnail.max' => 'The thumbnail may not be larger than 2MB',
            'categories.required' => 'You must select at least one category',
            'categories.*.exists' => 'One or more selected categories do not exist',
        ];
    }
}

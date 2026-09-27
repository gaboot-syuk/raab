<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class HalamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pages.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'array'],
            'judul.id' => ['required', 'string', 'max:200'],
            'judul.en' => ['nullable', 'string', 'max:200'],

            'slug' => ['required', 'array'],
            'slug.id' => ['required', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'slug.en' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],

            'ringkasan' => ['nullable', 'array'],
            'ringkasan.id' => ['nullable', 'string', 'max:500'],
            'ringkasan.en' => ['nullable', 'string', 'max:500'],

            'konten' => ['required', 'array'],
            'konten.id' => ['required', 'string', 'max:100000'],
            'konten.en' => ['nullable', 'string', 'max:100000'],

            'seo_judul' => ['nullable', 'array'],
            'seo_judul.id' => ['nullable', 'string', 'max:200'],
            'seo_judul.en' => ['nullable', 'string', 'max:200'],

            'seo_deskripsi' => ['nullable', 'array'],
            'seo_deskripsi.id' => ['nullable', 'string', 'max:300'],
            'seo_deskripsi.en' => ['nullable', 'string', 'max:300'],

            'status' => ['required', 'in:draft,terbit'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'judul.id' => 'judul (Indonesia)',
            'slug.id' => 'slug (Indonesia)',
            'konten.id' => 'isi halaman (Indonesia)',
            'status' => 'status',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'judul.id.required' => 'Judul versi Indonesia wajib diisi.',
            'konten.id.required' => 'Isi halaman versi Indonesia wajib diisi.',
            'slug.id.regex' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
            'slug.en.regex' => 'Slug Inggris hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
        ];
    }
}

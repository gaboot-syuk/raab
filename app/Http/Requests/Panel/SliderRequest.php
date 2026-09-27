<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class SliderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sliders.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'array'],
            'judul.id' => ['required', 'string', 'max:190'],
            'judul.en' => ['nullable', 'string', 'max:190'],

            'subjudul' => ['nullable', 'array'],
            'subjudul.id' => ['nullable', 'string', 'max:500'],
            'subjudul.en' => ['nullable', 'string', 'max:500'],

            'label_tombol' => ['nullable', 'array'],
            'label_tombol.id' => ['nullable', 'string', 'max:60'],
            'label_tombol.en' => ['nullable', 'string', 'max:60'],

            'tautan_tombol' => ['nullable', 'string', 'max:255'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'aktif' => ['boolean'],
            'mulai_pada' => ['nullable', 'date'],
            'berakhir_pada' => ['nullable', 'date', 'after_or_equal:mulai_pada'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'judul.id' => 'judul (Indonesia)',
            'subjudul.id' => 'subjudul (Indonesia)',
            'label_tombol.id' => 'label tombol (Indonesia)',
            'tautan_tombol' => 'tautan tombol',
            'media_id' => 'gambar latar',
            'berakhir_pada' => 'tanggal berakhir',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'berakhir_pada.after_or_equal' => 'Tanggal berakhir tidak boleh lebih awal dari tanggal mulai.',
            'judul.id.required' => 'Judul versi Indonesia wajib diisi.',
        ];
    }
}

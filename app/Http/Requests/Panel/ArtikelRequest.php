<?php

namespace App\Http\Requests\Panel;

use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArtikelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('articles.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $beritaAcara = $this->input('tipe') === Article::TIPE_BERITA_ACARA;

        return [
            'tipe' => ['required', Rule::in(array_keys(Article::TIPE))],
            'kategori_id' => ['nullable', 'integer', 'exists:article_categories,id'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],

            'judul' => ['required', 'array'],
            'judul.id' => ['required', 'string', 'min:3', 'max:190'],
            'judul.en' => ['nullable', 'string', 'max:190'],

            'slug' => ['nullable', 'array'],
            'slug.id' => ['nullable', 'string', 'max:190'],
            'slug.en' => ['nullable', 'string', 'max:190'],

            'ringkasan' => ['nullable', 'array'],
            'ringkasan.id' => ['nullable', 'string', 'max:500'],
            'ringkasan.en' => ['nullable', 'string', 'max:500'],

            'konten' => [$beritaAcara ? 'nullable' : 'required', 'array'],
            'konten.id' => [$beritaAcara ? 'nullable' : 'required', 'string', 'min:50'],
            'konten.en' => ['nullable', 'string'],

            'seo_judul' => ['nullable', 'array'],
            'seo_judul.id' => ['nullable', 'string', 'max:190'],
            'seo_judul.en' => ['nullable', 'string', 'max:190'],

            'seo_deskripsi' => ['nullable', 'array'],
            'seo_deskripsi.id' => ['nullable', 'string', 'max:300'],
            'seo_deskripsi.en' => ['nullable', 'string', 'max:300'],

            'tag' => ['nullable', 'string', 'max:500'],
            'unggulan' => ['boolean'],
            'dijadwalkan_pada' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(array_keys(Article::STATUS))],

            // --- Berita acara ---
            'nomor_dokumen' => [$beritaAcara ? 'required' : 'nullable', 'string', 'max:120'],
            'tanggal_agenda' => [$beritaAcara ? 'required' : 'nullable', 'date'],
            'agenda' => [$beritaAcara ? 'required' : 'nullable', 'string', 'max:2000'],
            'keputusan' => [$beritaAcara ? 'required' : 'nullable', 'string', 'max:5000'],
            'penandatangan' => [$beritaAcara ? 'required' : 'nullable', 'string', 'max:160'],
            'jabatan_penandatangan' => [$beritaAcara ? 'required' : 'nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'judul.id' => 'judul (Indonesia)',
            'konten.id' => 'isi artikel (Indonesia)',
            'konten.en' => 'isi artikel (Inggris)',
            'tipe' => 'tipe artikel',
            'kategori_id' => 'kategori',
            'cover_media_id' => 'gambar sampul',
            'nomor_dokumen' => 'nomor dokumen',
            'tanggal_agenda' => 'tanggal agenda',
            'penandatangan' => 'penandatangan',
            'jabatan_penandatangan' => 'jabatan penandatangan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'konten.id.required' => 'Isi artikel wajib diisi.',
            'konten.id.min' => 'Isi artikel minimal :min karakter.',
            'nomor_dokumen.required' => 'Berita acara wajib memuat nomor dokumen.',
            'keputusan.required' => 'Berita acara wajib memuat keputusan rapat.',
        ];
    }
}

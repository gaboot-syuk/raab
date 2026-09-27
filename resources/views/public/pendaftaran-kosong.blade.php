@extends('layouts.public')

@section('judul', __('pendaftaran.kosong.judul'))
@section('deskripsi', __('umum.nama_organisasi'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('pendaftaran.kosong.judul')"
        :deskripsi="__('pendaftaran.kosong.teks', ['jenis' => $label])"
        :remah="[__('umum.menu.layanan'), __('pendaftaran.judul')]"
    />

    <div class="mx-auto max-w-3xl px-4 py-16 text-center lg:px-6">
        <p class="brutal bg-paper-alt p-8 text-sm text-muted">
            {{ __('pendaftaran.kosong.teks', ['jenis' => $label]) }}
        </p>

        <a href="{{ route('public.pendaftaran') }}" class="brutal brutal-hover mt-6 inline-block bg-accent-400 px-6 py-3 font-bold text-primary-800">
            {{ __('pendaftaran.judul') }}
        </a>
    </div>
@endsection

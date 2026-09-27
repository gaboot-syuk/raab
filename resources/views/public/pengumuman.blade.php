@extends('layouts.public')

@section('judul', __('pengumuman.judul'))
@section('deskripsi', __('pengumuman.intro'))

@section('konten')
    <x-public.judul-halaman
        :judul="__('pengumuman.judul')"
        :deskripsi="__('pengumuman.intro')"
        :remah="[__('umum.menu.pengumuman')]"
    />

    <div class="mx-auto max-w-3xl px-4 py-10 lg:px-6">
        @if ($daftar->isEmpty())
            <div class="brutal bg-paper p-6">
                <p class="font-display text-lg">{{ __('pengumuman.kosong') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('pengumuman.kosong_teks') }}</p>
            </div>
        @else
            <ul class="space-y-4">
                @foreach ($daftar as $p)
                    <li class="brutal bg-paper p-5" @class(['border-l-8 border-l-accent-400' => $p->is_pinned])>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                            {{ __('pengumuman.daftar.diterbitkan') }}: {{ $p->publish_at?->translatedFormat('d F Y') }}
                            @if ($p->is_pinned)
                                · <span class="border-2 border-ink bg-accent-400 px-1.5 py-0.5">{{ __('pengumuman.daftar.disematkan') }}</span>
                            @endif
                        </p>

                        <h2 class="mt-1 font-display text-xl leading-tight">{{ $p->judulTeks() }}</h2>

                        <div class="mt-3 space-y-3 text-sm leading-relaxed">
                            @foreach (preg_split('/\n\s*\n/', trim($p->isiTeks())) as $paragraf)
                                <p class="whitespace-pre-line">{{ $paragraf }}</p>
                            @endforeach
                        </div>

                        <p class="mt-3 border-t-2 border-ink/10 pt-2 text-xs text-muted">
                            @if ($p->expire_at)
                                {{ __('pengumuman.daftar.berlaku_sampai') }}: {{ $p->expire_at->translatedFormat('d F Y, H:i') }}
                            @else
                                {{ __('pengumuman.daftar.tanpa_batas') }}
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection

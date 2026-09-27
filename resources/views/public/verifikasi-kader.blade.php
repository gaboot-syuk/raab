@extends('layouts.public')

@section('judul', __('kartu.judul'))
@section('deskripsi', __('kartu.intro'))

@section('konten')
    @php
        $anggota = $kartu?->member;
    @endphp

    <x-public.judul-halaman
        :judul="__('kartu.judul')"
        :deskripsi="__('kartu.intro')"
        :remah="[__('kartu.remah')]"
    />

    <div class="mx-auto max-w-3xl px-4 py-10 lg:px-6">
        {{--
            KEPUTUSANNYA DITARUH DI ATAS, bukan di bawah tabel data.
            Orang yang memindai kartu ingin satu jawaban secepat mungkin:
            sah atau tidak. Rincian identitas hanya pendukung jawaban itu.
        --}}
        <div class="brutal {{ $sah ? 'bg-accent-100' : 'bg-accent-400' }} p-5">
            <p class="font-display text-2xl uppercase">
                {{ $sah ? __('kartu.sah.judul') : __('kartu.tidak_sah.judul') }}
            </p>

            <p class="mt-1 text-sm">
                {{ $sah ? __('kartu.sah.teks') : __('kartu.tidak_sah.teks') }}
            </p>

            @if ($alasan)
                <p class="mt-2 text-sm font-bold">
                    {{ __('kartu.alasan.'.$alasan) }}
                </p>
            @endif
        </div>

        @if ($sah)
            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="brutal bg-paper p-4 sm:col-span-2">
                    <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.nama') }}</dt>
                    <dd class="mt-1 font-display text-xl">{{ $anggota?->nama_lengkap ?? '—' }}</dd>
                </div>

                <div class="brutal bg-paper p-4">
                    <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.nomor_kartu') }}</dt>
                    <dd class="mt-1 font-mono text-sm">{{ $kartu->nomor_kartu }}</dd>
                </div>

                <div class="brutal bg-paper p-4">
                    <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.jalur') }}</dt>
                    <dd class="mt-1 text-sm">
                        {{ \App\Models\Member::JALUR[$anggota?->jalur] ?? '—' }}
                    </dd>
                </div>

                @if ($anggota?->unit)
                    <div class="brutal bg-paper p-4">
                        <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.unit') }}</dt>
                        <dd class="mt-1 text-sm">{{ $anggota->unit->nama }}</dd>
                    </div>
                @endif

                <div class="brutal bg-paper p-4">
                    <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.berlaku') }}</dt>
                    <dd class="mt-1 text-sm">
                        {{ $kartu->berlaku_sampai?->translatedFormat('d F Y') ?? __('kartu.bidang.tanpa_batas') }}
                    </dd>
                </div>

                @if ($kartu->diterbitkan_pada)
                    <div class="brutal bg-paper p-4">
                        <dt class="text-xs font-bold uppercase text-muted">{{ __('kartu.bidang.diterbitkan') }}</dt>
                        <dd class="mt-1 text-sm">{{ $kartu->diterbitkan_pada->translatedFormat('d F Y') }}</dd>
                    </div>
                @endif
            </dl>
        @endif

        <p class="mt-6 text-xs leading-relaxed text-muted">
            {{ __('kartu.privasi') }}
        </p>

        <a href="{{ url('/') }}" class="brutal-sm brutal-hover mt-4 inline-block bg-paper px-4 py-2 text-sm font-bold">
            {{ __('kartu.kembali') }}
        </a>
    </div>
@endsection

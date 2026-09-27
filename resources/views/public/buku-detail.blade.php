@extends('layouts.public')

@section('judul', $buku->judulTeks())
@section('deskripsi', Str::limit(strip_tags((string) $buku->getTranslation('sinopsis', 'id', false)), 150))

@section('konten')
    <x-public.judul-halaman
        :judul="$buku->judulTeks()"
        :deskripsi="$buku->penulis"
        :remah="[__('umum.menu.layanan'), __('pustaka.pustaka.judul'), $buku->judulTeks()]"
    />

    <div class="mx-auto max-w-5xl px-4 py-10 lg:px-6">
        <div class="grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-1">
                @if ($buku->cover_media_id && $cover = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($buku->cover_media_id))
                    <img src="{{ $cover->hasGeneratedConversion('sedang') ? $cover->getUrl('sedang') : $cover->getUrl() }}"
                         alt="{{ $buku->judulTeks() }}" class="brutal w-full object-cover">
                @endif
            </div>

            <div class="lg:col-span-2">
                <dl class="brutal grid gap-3 bg-paper p-5 sm:grid-cols-2">
                    @if ($buku->penulis)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('pustaka.pustaka.penulis') }}</dt>
                            <dd>{{ $buku->penulis }}</dd>
                        </div>
                    @endif

                    @if ($buku->penerbit)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('pustaka.pustaka.penerbit') }}</dt>
                            <dd>{{ $buku->penerbit }}</dd>
                        </div>
                    @endif

                    @if ($buku->tahun_terbit)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('pustaka.pustaka.tahun') }}</dt>
                            <dd>{{ $buku->tahun_terbit }}</dd>
                        </div>
                    @endif

                    @if ($buku->isbn)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('pustaka.pustaka.isbn') }}</dt>
                            <dd class="font-mono text-sm">{{ $buku->isbn }}</dd>
                        </div>
                    @endif

                    @if ($buku->ddc)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('pustaka.pustaka.ddc') }}</dt>
                            <dd class="font-mono text-sm">{{ $buku->ddc }}</dd>
                        </div>
                    @endif

                    @if ($buku->jumlah_halaman)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('pustaka.pustaka.halaman') }}</dt>
                            <dd>{{ $buku->jumlah_halaman }}</dd>
                        </div>
                    @endif

                    @if ($buku->rak)
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">{{ __('pustaka.pustaka.rak') }}</dt>
                            <dd>{{ $buku->rak }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs font-bold uppercase text-muted">{{ __('pustaka.pustaka.eksemplar') }}</dt>
                        <dd>
                            {{ $buku->eksemplarTersedia() }} / {{ $buku->totalEksemplar() }} {{ __('pustaka.pustaka.tersedia') }}
                            @if ($jumlahAntrian > 0)
                                · {{ $jumlahAntrian }} {{ __('pustaka.pustaka.antrian') }}
                            @endif
                        </dd>
                    </div>
                </dl>

                {{-- Aksi peminjaman --}}
                <div class="mt-4">
                    @if (! $anggota)
                        <p class="brutal bg-paper-alt p-4 text-sm text-muted">{{ __('pustaka.pustaka.masuk_untuk_meminjam') }}</p>
                    @elseif ($antrian && $antrian->status === \App\Models\BookReservation::STATUS_SIAP)
                        {{-- Gilirannya sudah tiba: eksemplar ini sedang ditahan untuknya,
                             jadi hitungan "bebas" nol bukan alasan untuk menolak. --}}
                        <p class="brutal bg-accent-100 p-4 text-sm font-bold">{{ __('pustaka.pustaka.siap_diambil') }}</p>
                        <form method="POST" action="{{ route('anggota.pustaka.pinjam') }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="book_id" value="{{ $buku->id }}">
                            <button type="submit" class="brutal brutal-hover bg-accent-400 px-6 py-3 font-bold text-primary-800">
                                {{ __('pustaka.pustaka.ambil_sekarang') }}
                            </button>
                        </form>
                    @elseif ($antrian)
                        <p class="brutal bg-accent-100 p-4 text-sm font-bold">
                            {{ __('pustaka.pustaka.sudah_antri') }}
                            ({{ $antrian->labelStatus() }} · {{ $antrian->posisi }})
                        </p>
                    @elseif ($buku->eksemplarBebas() > 0)
                        <form method="POST" action="{{ route('anggota.pustaka.pinjam') }}">
                            @csrf
                            <input type="hidden" name="book_id" value="{{ $buku->id }}">
                            <button type="submit" class="brutal brutal-hover bg-accent-400 px-6 py-3 font-bold text-primary-800">
                                {{ __('pustaka.pustaka.ajukan') }}
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('anggota.pustaka.antri') }}">
                            @csrf
                            <input type="hidden" name="book_id" value="{{ $buku->id }}">
                            <button type="submit" class="brutal brutal-hover bg-paper px-6 py-3 font-bold">
                                {{ __('pustaka.pustaka.masuk_antrian') }}
                            </button>
                        </form>
                    @endif
                </div>

                @if ($sinopsis = $buku->getTranslation('sinopsis', app()->getLocale(), false) ?: $buku->getTranslation('sinopsis', 'id', false))
                    <section class="brutal mt-6 bg-paper p-5">
                        <h2 class="font-display text-lg">{{ __('pustaka.pustaka.sinopsis') }}</h2>
                        <div class="mt-2 text-sm">{!! nl2br(e($sinopsis)) !!}</div>
                    </section>
                @endif
            </div>
        </div>

        @if ($bukuLain->isNotEmpty())
            <section class="mt-10">
                <h2 class="font-display text-xl">{{ __('pustaka.pustaka.buku_lain') }}</h2>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($bukuLain as $lain)
                        <li class="brutal-sm brutal-hover bg-paper p-3">
                            <a href="{{ route('public.perpustakaan.detail', $lain->getTranslation('slug', 'id')) }}" class="text-sm font-bold hover:underline">
                                {{ $lain->judulTeks() }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection

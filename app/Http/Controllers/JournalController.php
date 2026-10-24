<?php

namespace App\Http\Controllers;

use App\Models\{InternshipRoster, Journal, JournalAttachment, JournalEvent, Placement};
use App\Support\{Access, Audit, JournalDateFilter};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class JournalController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:120',
            'status' => 'nullable|in:draft,submitted,revision,approved,rejected',
            'period_id' => 'nullable|integer|exists:internship_periods,id',
        ] + JournalDateFilter::rules($request));

        $hasJournalFilter = ! empty($filters['status']) || ! empty($filters['date_from']) || ! empty($filters['date_to']);
        $items = Access::placements($request->user())
            ->with(['student.user', 'company', 'period'])
            ->withCount([
                'journals',
                'journals as draft_journals_count' => fn ($q) => $q->where('status', 'draft'),
                'journals as submitted_journals_count' => fn ($q) => $q->where('status', 'submitted'),
                'journals as revision_journals_count' => fn ($q) => $q->where('status', 'revision'),
                'journals as rejected_journals_count' => fn ($q) => $q->where('status', 'rejected'),
                'journals as approved_journals_count' => fn ($q) => $q->where('status', 'approved'),
                'journals as industry_validated_count' => fn ($q) => $q->whereColumn('validated_revision','revision'),
            ])
            ->withMax('journals', 'activity_date')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('student', fn ($s) => $s->where('nis','like','%'.$term.'%')->orWhereHas('user',fn ($u) => $u->where('name','like','%'.$term.'%'))))
            ->when($filters['period_id'] ?? null, fn ($q, $periodId) => $q->where('internship_period_id', $periodId))
            ->when($hasJournalFilter, function ($q) use ($filters) {
                $q->whereHas('journals', function ($journal) use ($filters) {
                    $journal->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                        ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('activity_date', '>=', $date))
                        ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('activity_date', '<=', $date));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $periods = \App\Models\InternshipPeriod::query()
            ->whereIn('id', Access::placements($request->user())->select('internship_period_id'))
            ->latest()
            ->get();

        return view('journals.index', compact('items', 'periods'));
    }

    public function show(Request $request, Placement $placement)
    {
        $placement = Access::placement($request->user(), $placement->id);
        $placement->load(['student.user', 'company', 'period', 'certificate']);

        $journals = Journal::with(['attachments', 'reviewer', 'events.actor', 'industryValidations.mentor'])
            ->where('placement_id', $placement->id)
            ->orderByDesc('activity_date')
            ->paginate(10);

        $events = JournalEvent::with(['journal', 'actor'])
            ->whereHas('journal', fn ($q) => $q->where('placement_id', $placement->id))
            ->latest()
            ->limit(100)
            ->get();

        $counts = Journal::where('placement_id', $placement->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $canWrite = $request->user()->isRole('siswa')
            && $placement->roster?->internship_starts_at
            && $placement->roster?->internship_ends_at
            && $placement->roster?->internship_status !== 'not_internship';

        $editableJournals = $journals->getCollection()
            ->mapWithKeys(fn ($journal) => [$journal->id => [
                'date' => $journal->activity_date->format('Y-m-d'),
                'activity' => $journal->activity,
                'outcome' => $journal->outcome,
                'obstacles' => $journal->obstacles,
                'revision' => $journal->revision,
                'work_area' => $journal->work_area,
                'starts_time' => $journal->starts_time,
                'ends_time' => $journal->ends_time,
                'equipment_notes' => $journal->equipment_notes,
                'industry_score' => $journal->industry_score,
            ]]);

        return view('journals.show', compact('placement', 'journals', 'events', 'counts', 'canWrite', 'editableJournals'));
    }

    public function exportPdf(Request $request)
    {
        $filters = $request->validate([
            'status' => 'nullable|in:draft,submitted,revision,approved,rejected',
            'q' => 'nullable|string|max:120',
            'placement_id' => 'nullable|integer',
            'period_id' => 'nullable|integer|exists:internship_periods,id',
        ] + JournalDateFilter::rules($request));

        if (! empty($filters['placement_id'])) {
            Access::placement($request->user(), (int) $filters['placement_id']);
        }

        $items = Journal::with(['placement.student.user', 'placement.company', 'attachments', 'industryValidations'])
            ->whereIn('placement_id', Access::placements($request->user())->select('id'))
            ->when($filters['placement_id'] ?? null, fn ($q, $id) => $q->where('placement_id', $id))
            ->when($filters['period_id'] ?? null, fn ($q, $id) => $q->whereHas('placement', fn ($placement) => $placement->where('internship_period_id', $id)))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('placement.student', fn ($s) => $s->where('nis','like','%'.$term.'%')->orWhereHas('user',fn ($u) => $u->where('name','like','%'.$term.'%'))))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('activity_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('activity_date', '<=', $date))
            ->orderBy('activity_date')
            ->limit(301)
            ->get();

        if ($items->count() > 300) {
            throw ValidationException::withMessages(['export'=>'Rekap PDF melebihi 300 entri. Persempit tanggal/siswa atau gunakan Excel per siswa; tidak ada data yang dipotong diam-diam.']);
        }

        return Pdf::loadView('journals.pdf', compact('items'))->setPaper('a4', 'landscape')
            ->download('rekap-jurnal-simmag-'.now()->format('Ymd-His').'.pdf');
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isRole('siswa'), 403);
        $data = $request->validate([
            'placement_id' => 'required|integer',
            'activity_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'activity' => 'required|string|min:3|max:5000',
            'outcome' => 'nullable|string|max:5000',
            'obstacles' => 'nullable|string|max:3000',
            'work_area' => 'nullable|string|max:150',
            'starts_time' => 'nullable|required_with:ends_time|date_format:H:i',
            'ends_time' => 'nullable|required_with:starts_time|date_format:H:i|after_or_equal:starts_time',
            'equipment_notes' => 'nullable|string|max:3000',
            'industry_score' => 'nullable|numeric|between:0,100|decimal:0,2',
            'revision' => 'nullable|integer|min:0',
            'intent' => 'required|in:draft,submitted',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|mimes:jpg,jpeg,png|max:4096',
        ], [
            'ends_time.after_or_equal'=>'Jam selesai harus sama dengan atau setelah jam mulai.',
            'ends_time.required_with'=>'Isi jam selesai jika jam mulai diisi.',
            'starts_time.required_with'=>'Isi jam mulai jika jam selesai diisi.',
            'industry_score.between'=>'Nilai industri harus berada antara 0 dan 100.',
            'industry_score.decimal'=>'Nilai industri maksimal dua angka desimal.',
            'activity_date.before_or_equal'=>'Tanggal kegiatan tidak boleh melewati hari ini.',
            'photos.*.max'=>'Ukuran setiap foto maksimal 4 MB.',
            'photos.*.image'=>'Lampiran harus berupa foto.',
            'photos.*.mimes'=>'Gunakan foto berformat JPG atau PNG.',
        ]);

        $placement = Access::placement($request->user(), $data['placement_id']);
        if (! $placement->roster?->internship_starts_at || ! $placement->roster?->internship_ends_at || $placement->roster->internship_status === 'not_internship') {
            throw ValidationException::withMessages(['placement_id' => 'Jadwal Kontrol PKL belum lengkap atau siswa tidak mengikuti PKL.']);
        }
        if ($data['activity_date'] < $placement->roster->internship_starts_at->toDateString() || $data['activity_date'] > $placement->roster->internship_ends_at->toDateString()) {
            return back()->withErrors(['activity_date' => 'Tanggal jurnal harus berada dalam jadwal siswa di Kontrol PKL.'])->withInput();
        }

        $journal = Journal::where('placement_id', $placement->id)->whereDate('activity_date', $data['activity_date'])->first();

        $existingPhotos = $journal?->attachments()->count() ?? 0;
        $newPhotoCount = count($request->file('photos', []));
        if ($existingPhotos + $newPhotoCount > 5) {
            throw ValidationException::withMessages(['photos' => 'Total foto pada satu jurnal maksimal lima, termasuk foto dari pengiriman sebelumnya.']);
        }

        $uploaded = [];
        try {
            foreach ($request->file('photos', []) as $file) {
                $path = $file->store('journals', 'local');
                if (! $path) {
                    throw new \RuntimeException('Gagal menyimpan foto.');
                }
                $uploaded[] = ['path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'size' => $file->getSize()];
            }

            DB::transaction(function () use (&$journal, $placement, $data, $uploaded, $request) {
                // Lock the source schedule first, consistently with bulk placement operations.
                // A coordinator may have corrected it while photos were uploading.
                $roster = InternshipRoster::whereKey($placement->internship_roster_id)->lockForUpdate()->first();
                if (! $roster?->internship_starts_at || ! $roster?->internship_ends_at || $roster->internship_status === 'not_internship') {
                    throw ValidationException::withMessages(['placement_id'=>'Jadwal Kontrol PKL berubah atau belum lengkap. Muat ulang sebelum menyimpan.']);
                }
                if ($data['activity_date'] < $roster->internship_starts_at->toDateString() || $data['activity_date'] > $roster->internship_ends_at->toDateString()) {
                    throw ValidationException::withMessages(['activity_date'=>'Tanggal jurnal tidak lagi sesuai jadwal terbaru di Kontrol PKL.']);
                }
                Placement::whereKey($placement->id)->lockForUpdate()->firstOrFail();
                $journal = Journal::where('placement_id', $placement->id)->whereDate('activity_date', $data['activity_date'])->lockForUpdate()->first();
                if ((int) ($data['revision'] ?? 0) !== ($journal?->revision ?? 0)) {
                    throw ValidationException::withMessages(['revision' => 'Jurnal berubah atau tanggal sudah memiliki jurnal. Buka tombol Edit pada entri terbaru sebelum menyimpan.']);
                }
                if ($journal) {
                    if ($journal->attachments()->count() + count($uploaded) > 5) {
                        throw ValidationException::withMessages(['photos' => 'Total foto pada satu jurnal maksimal lima, termasuk foto dari pengiriman sebelumnya.']);
                    }
                }
                $previousStatus = $journal?->status;
                $before = $journal?->contentSnapshot();
                if (! $journal) {
                    $journal = new Journal(['placement_id' => $placement->id, 'activity_date' => $data['activity_date']]);
                }
                $journal->fill([
                    'activity' => $data['activity'], 'outcome' => $data['outcome'] ?? '', 'obstacles' => $data['obstacles'] ?? null,
                    'status' => $data['intent'], 'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null,
                    'work_area'=>$data['work_area'] ?? null, 'starts_time'=>$data['starts_time'] ?? null, 'ends_time'=>$data['ends_time'] ?? null,
                    'equipment_notes'=>$data['equipment_notes'] ?? null, 'industry_score'=>$data['industry_score'] ?? null,
                    'revision'=>($journal->exists ? $journal->revision : 0) + 1, 'reviewed_revision'=>null, 'validated_revision'=>null,
                ])->save();
                foreach ($uploaded as $file) {
                    $journal->attachments()->create($file);
                }
                $note = $data['intent'] === 'submitted'
                    ? ($previousStatus ? 'Jurnal diperbaiki dan dikirim ulang.' : 'Jurnal tercatat; review sekolah dan validasi industri bersifat terpisah.')
                    : ($previousStatus ? 'Perubahan jurnal disimpan sebagai draf.' : 'Jurnal dibuat sebagai draf.');
                $journal->events()->create(['actor_id' => $request->user()->id, 'status' => $data['intent'], 'note' => $note]);
                Audit::record('journal.'.($data['intent'] === 'submitted' ? 'submitted' : 'saved'), $journal, ['before'=>$before,'after'=>$journal->contentSnapshot()]);
            });
        } catch (\Throwable $exception) {
            foreach ($uploaded as $file) {
                Storage::disk('local')->delete($file['path']);
            }
            throw $exception;
        }

        return redirect()->route('journals.show', $placement)->with('success', 'Jurnal berhasil disimpan.');
    }

    public function review(Request $request, Journal $journal)
    {
        abort_unless($request->user()->isRole('koordinator','guru'), 403);
        Access::placement($request->user(), $journal->placement_id);
        $data = $request->validate([
            'status' => 'nullable|in:approved',
            'revision' => 'required|integer|min:1',
            'review_note' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($journal, $data, $request) {
            $locked = Journal::whereKey($journal->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status !== 'draft' && $locked->revision === (int)$data['revision'], 422, 'Versi jurnal berubah atau masih draf. Muat ulang.');
            $locked->update([
                'status' => 'approved', 'reviewed_revision'=>$locked->revision, 'review_note' => $data['review_note'] ?? null,
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
            ]);
            $locked->events()->create([
                'actor_id' => $request->user()->id,
                'status' => 'approved',
                'note' => $data['review_note'] ?? 'Jurnal ditinjau sekolah; bukan pengesahan industri.',
            ]);
            Audit::record('journal.reviewed', $locked, $data);
        });

        return back()->with('success', 'Pemeriksaan jurnal telah dicatat.');
    }

    public function photo(Request $request, JournalAttachment $attachment)
    {
        Access::placement($request->user(), $attachment->journal->placement_id);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path, null, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

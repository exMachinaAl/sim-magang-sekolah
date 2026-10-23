<?php

namespace App\Support;

use App\Models\Placement;
use App\Models\TimelineEvent;
use App\Models\TimelineEventCompletion;

class TimelineProgress
{
    public function forPlacement(
        TimelineEvent $event,
        Placement $placement,
        ?TimelineEventCompletion $completion = null
    ): array {
        $progress = $event->progress_source === 'manual'
            ? $this->manual($completion)
            : $this->automatic($event, $placement);

        $progress['is_automatic'] = $event->progress_source !== 'manual';
        $progress['is_complete'] = $progress['status'] === 'completed';
        $progress['is_overdue'] = ! $progress['is_complete'] && now()->startOfDay()->gt($event->ends_at);

        if ($progress['is_overdue'] && ! in_array($progress['status'], ['revision_required', 'blocked'], true)) {
            $progress['status'] = 'overdue';
            $progress['label'] = 'Terlambat';
            $progress['class'] = 'revision';
        }

        $progress['needs_attention'] = $progress['is_overdue']
            || in_array($progress['status'], ['revision_required', 'blocked'], true);

        return $progress;
    }

    public function blockedByDependency(string $dependencyTitle, bool $automatic): array
    {
        return [
            'status' => 'blocked',
            'label' => 'Menunggu prasyarat',
            'class' => 'blocked',
            'reason' => 'Selesaikan tahap “'.$dependencyTitle.'” terlebih dahulu.',
            'is_automatic' => $automatic,
            'is_complete' => false,
            'is_overdue' => false,
            'needs_attention' => true,
        ];
    }

    private function manual(?TimelineEventCompletion $completion): array
    {
        if ($completion) {
            return $this->result('completed', 'Selesai', 'approved', 'Ditandai selesai secara manual.');
        }

        return $this->result('not_started', 'Belum selesai', 'submitted', 'Menunggu tindakan pemilik tahap.');
    }

    private function automatic(TimelineEvent $event, Placement $placement): array
    {
        return match ($event->progress_source) {
            'placement' => $this->placement($placement),
            'journals' => $this->journals($placement),
            'monitoring' => $this->monitoring($event, $placement),
            'assessment' => $this->assessment($placement),
            'certificate_approval' => $this->certificateApproval($placement),
            'certificate_distribution' => $this->certificateDistribution($placement),
            default => $this->result('not_started', 'Belum selesai', 'submitted', 'Sumber progres belum dikenali.'),
        };
    }

    private function placement(Placement $placement): array
    {
        if ($placement->internship_roster_id && $placement->company_id) {
            return $this->result('completed', 'Selesai', 'approved', 'Industri dan roster penempatan telah ditetapkan; jadwal mengikuti Kontrol PKL.');
        }

        return $this->result('not_started', 'Belum ditempatkan', 'submitted', 'Penempatan siswa belum aktif.');
    }

    private function journals(Placement $placement): array
    {
        $total = (int) ($placement->journals_count ?? 0);
        $approved = (int) ($placement->approved_journals_count ?? 0);
        $submitted = (int) ($placement->submitted_journals_count ?? 0);
        $draft = (int) ($placement->draft_journals_count ?? 0);
        $revision = (int) ($placement->revision_journals_count ?? 0);

        if ($revision > 0) {
            return $this->result('revision_required', 'Perlu revisi', 'revision', $revision.' jurnal perlu diperbaiki.');
        }
        if ($submitted > 0) {
            return $this->result('waiting_review', 'Menunggu review', 'submitted', $submitted.' jurnal menunggu pemeriksaan guru.');
        }
        if ($total > 0 && $approved === $total) {
            return $this->result('completed', 'Selesai', 'approved', $approved.' jurnal telah disetujui.');
        }
        if ($draft > 0 || $total > 0) {
            return $this->result('in_progress', 'Sedang dikerjakan', 'in-progress', $draft.' jurnal masih berupa draf.');
        }

        return $this->result('not_started', 'Belum ada jurnal', 'submitted', 'Siswa belum membuat jurnal.');
    }

    private function monitoring(TimelineEvent $event, Placement $placement): array
    {
        $count = (int) ($placement->monitoring_count ?? 0);
        $required = max(1, (int) $event->required_count);
        if ($count >= $required) {
            return $this->result('completed', 'Selesai', 'approved', $count.' dari '.$required.' monitoring tercatat.');
        }
        if ($count > 0) {
            return $this->result('in_progress', 'Sedang berjalan', 'in-progress', $count.' dari '.$required.' monitoring tercatat.');
        }

        return $this->result('not_started', 'Belum dimonitor', 'submitted', 'Belum ada catatan monitoring guru.');
    }

    private function assessment(Placement $placement): array
    {
        return match ($placement->assessment?->status) {
            'approved' => $this->result('completed', 'Selesai', 'approved', 'Penilaian telah disahkan koordinator.'),
            'submitted' => $this->result('waiting_review', 'Menunggu validasi', 'submitted', 'Penilaian menunggu validasi koordinator.'),
            'revision' => $this->result('revision_required', 'Perlu revisi', 'revision', 'Penilaian dikembalikan kepada guru.'),
            'draft' => $this->result('in_progress', 'Draf penilaian', 'in-progress', 'Guru belum mengirim penilaian.'),
            default => $this->result('not_started', 'Belum dinilai', 'submitted', 'Penilaian belum dibuat.'),
        };
    }

    private function certificateApproval(Placement $placement): array
    {
        $status = $placement->certificate?->status;
        if (in_array($status, ['awaiting_print', 'printed', 'sent_to_company', 'returned_to_school', 'ready_pickup', 'handed_to_student', 'completed'], true)) {
            return $this->result('completed', 'Tervalidasi', 'approved', 'Sertifikat telah disetujui dan dapat diproses.');
        }

        return match ($status) {
            'awaiting_approval' => $this->result('waiting_review', 'Menunggu validasi', 'submitted', 'Sertifikat menunggu persetujuan koordinator.'),
            'revision_required' => $this->result('revision_required', 'Perlu revisi', 'revision', 'Persetujuan sertifikat memerlukan revisi.'),
            default => $this->result('not_started', 'Belum dibuat', 'submitted', 'Sertifikat belum dibuat.'),
        };
    }

    private function certificateDistribution(Placement $placement): array
    {
        $status = $placement->certificate?->status;
        if ($status === 'completed') {
            return $this->result('completed', 'Selesai', 'approved', 'Sertifikat telah diserahkan dan proses ditutup.');
        }
        if ($status === 'handed_to_student') {
            return $this->result('in_progress', 'Sudah diserahkan', 'in-progress', 'Sertifikat telah diterima siswa, menunggu penutupan proses.');
        }
        if ($status) {
            return $this->result('in_progress', 'Dalam proses', 'in-progress', 'Status sertifikat: '.$placement->certificate->statusLabel().'.');
        }

        return $this->result('not_started', 'Belum tersedia', 'submitted', 'Sertifikat belum tersedia untuk didistribusikan.');
    }

    private function result(string $status, string $label, string $class, string $reason): array
    {
        return compact('status', 'label', 'class', 'reason');
    }
}

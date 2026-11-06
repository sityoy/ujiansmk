<?php

namespace App\Services\Attendance;

use App\Enums\AssignmentStatus;
use App\Enums\CheckinStatus;
use App\Enums\SessionStatus;
use App\Models\DailyCheckin;
use App\Models\ExamAssignment;

class AttendanceAvailability
{
    public function reason(ExamAssignment $assignment, ?DailyCheckin $checkin = null): ?string
    {
        $assignment->loadMissing('examSession.campus');
        $session = $assignment->examSession;
        if (! in_array($assignment->status, [AssignmentStatus::Scheduled, AssignmentStatus::Started], true)) {
            return 'Penugasan tidak aktif. Hubungi panitia untuk penempatan sesi atau susulan.';
        }
        if (! $session->starts_at->isSameDay($session->ends_at) || $session->ends_at->lte($session->starts_at)) {
            return 'Jadwal sesi tidak valid. Minta panitia memperbaiki tanggal dan durasi sesi.';
        }
        if ($session->status === SessionStatus::Closed || now()->gte($session->ends_at)) {
            return 'Sesi sudah berakhir. Hubungi panitia jika membutuhkan ujian susulan.';
        }
        if (! in_array($session->status, [SessionStatus::Published, SessionStatus::Active], true)) {
            return 'Sesi masih draf. Absensi tersedia setelah panitia menerbitkan sesi.';
        }
        if (! $session->campus->is_active) {
            return 'Lokasi sekolah sedang tidak aktif. Hubungi panitia.';
        }
        if (! $session->starts_at->isToday()) {
            return 'Absensi hanya tersedia pada tanggal ujian: '.$session->starts_at->format('d/m/Y').'.';
        }
        if (now()->lt($session->starts_at->copy()->subMinutes(60))) {
            return 'Absensi dibuka pukul '.$session->starts_at->copy()->subMinutes(60)->format('H:i').' (60 menit sebelum ujian).';
        }
        if ($checkin && $checkin->status !== CheckinStatus::Verified) {
            return 'Absensi sedang diperiksa atau ditolak. Hubungi pengawas; jangan mengirim ulang untuk melewati pemeriksaan.';
        }
        if ($checkin && $checkin->campus_id !== $session->campus_id) {
            return 'Absensi hari ini tercatat di lokasi lain. Hubungi pengawas.';
        }

        return null;
    }
}

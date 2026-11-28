<label class="block text-xs font-medium text-slate-300">Status baru
    <select name="is_active" data-student-status required class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm">
        <option value="">Pilih status</option>
        <option value="1">Aktif</option>
        <option value="0">Nonaktif</option>
    </select>
</label>
<label data-student-reason class="block text-xs font-medium text-slate-300">Alasan jika nonaktif
    <textarea name="inactive_reason" maxlength="500" rows="2" placeholder="Alasan yang akan dibaca siswa saat login"
        class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-sm"></textarea>
</label>

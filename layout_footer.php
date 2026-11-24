        </main>
    </div>
    <footer class="bg-gradient-to-r from-[#005a30] via-[#006838] to-[#004f2a] text-white border-t-4 border-[#D4AF37] py-3.5 px-6 text-center text-xs font-medium shadow-inner print:hidden">
        Copyright &copy; <?= date('Y') ?> Prakom Kemenag Lebak
    </footer>

    <!-- Popup Modal Layanan Kontak Helpdesk & Credit Pengembang (Tanpa Musik) -->
    <div id="helpdeskModal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-3 print:hidden" onclick="if(event.target===this) closeHelpdeskModal()">
        <style>
            .php-credit-mask {
                mask-image: linear-gradient(to bottom, transparent 0%, black 10%, black 90%, transparent 100%);
                -webkit-mask-image: linear-gradient(to bottom, transparent 0%, black 10%, black 90%, transparent 100%);
            }
        </style>
        <div class="relative bg-slate-950 text-slate-100 w-full max-w-4xl h-[88vh] max-h-[800px] rounded-2xl shadow-2xl border-2 border-[#D4AF37]/50 overflow-hidden flex flex-col justify-between select-none">
            <div class="w-full px-5 py-3.5 flex items-center justify-between border-b border-emerald-800/50 bg-slate-950/95">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white p-1.5 border border-[#D4AF37]/70 flex items-center justify-center shrink-0">
                        <span class="text-[#006838] font-black text-xs">KMG</span>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm font-bold tracking-wider text-white uppercase">Kemenag Kabupaten Lebak</div>
                        <div class="text-[11px] text-emerald-400 font-medium">Layanan Kontak Helpdesk &amp; Tim Pranata Komputer</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span id="phpCreditStatus" class="hidden sm:inline-block px-3 py-1 rounded-full bg-slate-900 border border-emerald-800/60 text-[11px] font-mono text-emerald-300">Scrolling (1.0x)</span>
                    <button type="button" onclick="closeHelpdeskModal()" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-rose-700 text-white text-xs font-bold">&times;</button>
                </div>
            </div>

            <div id="phpCreditsViewport" class="relative flex-1 w-full max-w-3xl mx-auto px-5 overflow-hidden php-credit-mask">
                <div id="phpCreditsTrack" class="absolute left-5 right-5 flex flex-col items-center text-center space-y-8 py-8">
                    <div class="space-y-3 pt-6">
                        <div class="inline-block px-4 py-1 rounded-full bg-emerald-950 border border-emerald-500/40 text-emerald-300 text-xs font-semibold uppercase">Kantor Kementerian Agama Kabupaten Lebak</div>
                        <h3 class="text-2xl sm:text-3xl font-black text-amber-300 tracking-wide">CREATED &amp; DEVELOPED BY:</h3>
                        <p class="text-sm font-bold text-emerald-300 uppercase">Tim Teknologi Informasi dan Prakom Kemenag Lebak</p>
                    </div>

                    <div class="w-full max-w-xl space-y-3">
                        <div class="p-4 rounded-2xl bg-slate-900/90 border border-emerald-500/40"><div class="text-xl font-bold text-emerald-300">Fadli Kurnia DC, S.Kom</div><div class="text-xs text-amber-300 mt-0.5">JF Prakom Ahli Muda</div></div>
                        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800"><div class="text-lg font-bold text-slate-100">Syachdin Achdian, S.Kom</div><div class="text-xs text-emerald-400 mt-0.5">JF Prakom Ahli Muda</div></div>
                        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800"><div class="text-lg font-bold text-slate-100">Ahmad Baidhawi, S.Kom</div><div class="text-xs text-emerald-400 mt-0.5">JF Prakom Ahli Pertama</div></div>
                        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800"><div class="text-lg font-bold text-slate-100">Muhamad Syahroni, S.Kom</div><div class="text-xs text-emerald-400 mt-0.5">JF Prakom Ahli Pertama</div></div>
                        <div class="p-4 rounded-2xl bg-slate-900/90 border border-amber-500/40"><div class="text-lg font-bold text-amber-300">Sinta Lintang Asih</div><div class="text-xs text-amber-400 mt-0.5">JF Statistisi Ahli Pertama</div></div>
                    </div>

                    <div id="phpCreditsContact" class="w-full max-w-xl pt-4 pb-10 space-y-5">
                        <div class="text-xs font-bold text-amber-400 tracking-wider uppercase">Helpdesk &amp; Layanan Kontak</div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <a href="https://wa.me/6287772600662" target="_blank" rel="noopener noreferrer" class="p-4 rounded-xl bg-slate-900 border border-emerald-500/40 hover:border-emerald-400">
                                <div class="text-[10px] font-bold text-slate-400 uppercase">Helpdesk WA</div>
                                <div class="text-xs font-bold text-emerald-300 font-mono mt-1">087772600662</div>
                            </a>
                            <a href="mailto:jfprakom@gmail.com" class="p-4 rounded-xl bg-slate-900 border border-amber-500/40 hover:border-amber-400">
                                <div class="text-[10px] font-bold text-slate-400 uppercase">Email Resmi</div>
                                <div class="text-xs font-bold text-amber-300 font-mono mt-1">jfprakom@gmail.com</div>
                            </a>
                            <a href="https://kemenaglebak.com" target="_blank" rel="noopener noreferrer" class="p-4 rounded-xl bg-slate-900 border border-teal-500/40 hover:border-teal-400">
                                <div class="text-[10px] font-bold text-slate-400 uppercase">Situs Web</div>
                                <div class="text-xs font-bold text-teal-300 font-mono mt-1">kemenaglebak.com</div>
                            </a>
                        </div>
                        <p class="text-xs italic text-slate-400">"Ikhlas Beramal - Melayani dengan Hati &amp; Inovasi Teknologi Informasi"</p>
                    </div>
                </div>
            </div>

            <div class="px-4 py-3 bg-slate-950 border-t border-emerald-900/60 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <button type="button" id="phpBtnToggle" onclick="togglePhpCredits()" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">Jeda</button>
                    <button type="button" onclick="resetPhpCredits()" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium">Ulangi</button>
                    <button type="button" onclick="jumpPhpContacts()" class="px-3 py-2 rounded-xl bg-amber-500/20 border border-amber-400/40 text-amber-300 text-xs font-bold">Lihat Kontak</button>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-slate-300">Kecepatan:</span>
                    <input type="range" min="0.5" max="3.0" step="0.1" value="1.0" oninput="phpCreditSpeed=parseFloat(this.value);document.getElementById('phpSpeedVal').textContent=phpCreditSpeed.toFixed(1)+'x';" class="accent-emerald-500 cursor-pointer">
                    <span id="phpSpeedVal" class="font-mono text-emerald-400">1.0x</span>
                </div>
                <button type="button" onclick="closeHelpdeskModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        let phpCreditRaf = null, phpCreditY = 0, phpCreditPlaying = true, phpCreditHovered = false, phpCreditSpeed = 1.0;
        function resetPhpCredits() {
            const vp = document.getElementById('phpCreditsViewport');
            const tr = document.getElementById('phpCreditsTrack');
            if (!vp || !tr) return;
            phpCreditY = vp.clientHeight * 0.85;
            tr.style.transform = 'translateY(' + phpCreditY + 'px)';
        }
        function togglePhpCredits() {
            phpCreditPlaying = !phpCreditPlaying;
            document.getElementById('phpBtnToggle').textContent = phpCreditPlaying ? 'Jeda' : 'Mulai';
            document.getElementById('phpCreditStatus').textContent = phpCreditPlaying ? 'Scrolling (' + phpCreditSpeed.toFixed(1) + 'x)' : 'Dijeda';
        }
        function jumpPhpContacts() {
            const vp = document.getElementById('phpCreditsViewport');
            const tr = document.getElementById('phpCreditsTrack');
            const ct = document.getElementById('phpCreditsContact');
            if (!vp || !tr || !ct) return;
            phpCreditPlaying = false;
            document.getElementById('phpBtnToggle').textContent = 'Mulai';
            document.getElementById('phpCreditStatus').textContent = 'Dijeda';
            phpCreditY = Math.min(0, vp.clientHeight - (ct.offsetTop + ct.offsetHeight + 24));
            tr.style.transform = 'translateY(' + phpCreditY + 'px)';
        }
        function animatePhpCredits() {
            const vp = document.getElementById('phpCreditsViewport');
            const tr = document.getElementById('phpCreditsTrack');
            if (vp && tr && phpCreditPlaying && !phpCreditHovered) {
                phpCreditY -= phpCreditSpeed;
                if (phpCreditY < -tr.clientHeight) phpCreditY = vp.clientHeight;
                tr.style.transform = 'translateY(' + phpCreditY + 'px)';
            }
            phpCreditRaf = requestAnimationFrame(animatePhpCredits);
        }
        function openHelpdeskModal() {
            const modal = document.getElementById('helpdeskModal');
            modal.classList.remove('hidden');
            phpCreditPlaying = true;
            document.getElementById('phpBtnToggle').textContent = 'Jeda';
            resetPhpCredits();
            if (phpCreditRaf) cancelAnimationFrame(phpCreditRaf);
            phpCreditRaf = requestAnimationFrame(animatePhpCredits);
        }
        function closeHelpdeskModal() {
            document.getElementById('helpdeskModal').classList.add('hidden');
            if (phpCreditRaf) cancelAnimationFrame(phpCreditRaf);
        }
        document.getElementById('phpCreditsViewport')?.addEventListener('mouseenter', () => { phpCreditHovered = true; });
        document.getElementById('phpCreditsViewport')?.addEventListener('mouseleave', () => { phpCreditHovered = false; });
    </script>
</body>
</html>

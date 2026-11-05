<!-- Shared AI progress card (gradual fill toward 90% while queued work runs). -->
<div id="ai-progress-card" class="hidden fixed top-20 right-4 z-[9999] w-72 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-xl" role="status" aria-live="polite">
    <div class="flex items-center gap-2">
        <span id="ai-progress-dot" class="h-3 w-3 flex-shrink-0 rounded-full bg-amber-400"></span>
        <span id="ai-progress-title" class="flex-1 truncate text-sm font-bold text-slate-900">Working…</span>
        <span id="ai-progress-pct" class="w-10 text-right text-xs font-bold tabular-nums text-slate-700">0%</span>
    </div>
    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
        <div id="ai-progress-fill" class="h-full rounded-full bg-[#26488C]" style="width: 0%; transition: width 0.4s ease;"></div>
    </div>
    <p id="ai-progress-line1" class="mt-2 truncate text-xs text-slate-600">Preparing…</p>
    <p id="ai-progress-line2" class="text-[11px] text-slate-400">0s elapsed</p>
</div>

<script>
(function () {
    const aiProgress = { runId: 0, tick: null, clock: null, pct: 0, startedAt: 0, hideTimer: null };

    function aiProgressEls() {
        return {
            card: document.getElementById('ai-progress-card'),
            dot: document.getElementById('ai-progress-dot'),
            title: document.getElementById('ai-progress-title'),
            fill: document.getElementById('ai-progress-fill'),
            pct: document.getElementById('ai-progress-pct'),
            line1: document.getElementById('ai-progress-line1'),
            line2: document.getElementById('ai-progress-line2'),
        };
    }

    function aiProgressStopTimers(runId) {
        if (runId !== aiProgress.runId) return true;
        if (aiProgress.tick) { clearInterval(aiProgress.tick); aiProgress.tick = null; }
        if (aiProgress.clock) { clearInterval(aiProgress.clock); aiProgress.clock = null; }
        if (aiProgress.hideTimer) { clearTimeout(aiProgress.hideTimer); aiProgress.hideTimer = null; }
        return false;
    }

    window.startAiProgress = function (title, detail) {
        const els = aiProgressEls();
        if (!els.card) return;
        if (aiProgress.tick) { clearInterval(aiProgress.tick); aiProgress.tick = null; }
        if (aiProgress.clock) { clearInterval(aiProgress.clock); aiProgress.clock = null; }
        if (aiProgress.hideTimer) { clearTimeout(aiProgress.hideTimer); aiProgress.hideTimer = null; }
        const runId = ++aiProgress.runId;
        aiProgress.pct = 0;
        aiProgress.startedAt = Date.now();
        els.card.classList.remove('hidden');
        els.title.textContent = title || 'Working…';
        els.dot.className = 'h-3 w-3 flex-shrink-0 rounded-full bg-amber-400';
        els.fill.className = 'h-full rounded-full bg-[#26488C]';
        els.fill.style.width = '0%';
        els.pct.textContent = '0%';
        els.line1.textContent = detail || 'Working…';
        els.line2.textContent = '0s elapsed';
        aiProgress.tick = setInterval(() => {
            if (runId !== aiProgress.runId) { clearInterval(aiProgress.tick); return; }
            aiProgress.pct = Math.min(90, aiProgress.pct + Math.max(0.6, (90 - aiProgress.pct) * 0.055));
            els.fill.style.width = aiProgress.pct.toFixed(0) + '%';
            els.pct.textContent = aiProgress.pct.toFixed(0) + '%';
        }, 400);
        aiProgress.clock = setInterval(() => {
            if (runId !== aiProgress.runId) { clearInterval(aiProgress.clock); return; }
            els.line2.textContent = Math.floor((Date.now() - aiProgress.startedAt) / 1000) + 's elapsed';
        }, 1000);
    };

    function aiProgressFinish(runId, ok, line1, longWait) {
        const els = aiProgressEls();
        if (!els.card || aiProgressStopTimers(runId)) return;
        if (ok) {
            aiProgress.pct = 100;
            els.fill.style.width = '100%';
            els.pct.textContent = '100%';
            els.dot.className = 'h-3 w-3 flex-shrink-0 rounded-full bg-green-500';
        } else if (longWait) {
            els.dot.className = 'h-3 w-3 flex-shrink-0 rounded-full bg-amber-500';
            els.fill.className = 'h-full rounded-full bg-amber-500';
        } else {
            els.dot.className = 'h-3 w-3 flex-shrink-0 rounded-full bg-red-500';
            els.fill.className = 'h-full rounded-full bg-red-500';
        }
        if (line1) els.line1.textContent = line1;
        aiProgress.hideTimer = setTimeout(() => {
            if (runId !== aiProgress.runId) return;
            els.card.classList.add('hidden');
            aiProgress.runId++;
        }, 2500);
    }

    window.finishAiProgress = function (doneText) {
        aiProgressFinish(aiProgress.runId, true, doneText || 'Done.');
    };

    window.waitAiProgress = function (note) {
        aiProgressFinish(aiProgress.runId, false, note || 'Taking longer than usual — it will finish automatically.', true);
    };

    window.failAiProgress = function (message) {
        aiProgressFinish(aiProgress.runId, false, message || 'Failed. Please try again.');
    };
})();
</script>

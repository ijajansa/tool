import './bootstrap';
import './phase2';

const search = document.querySelector('#tool-search');
if (search) {
    const cards = [...document.querySelectorAll('[data-search]')];
    search.addEventListener('input', () => {
        const query = search.value.trim().toLowerCase();
        let visible = 0;
        cards.forEach((card) => {
            const matches = card.dataset.search.includes(query);
            card.classList.toggle('hidden', !matches);
            visible += matches ? 1 : 0;
        });
        document.querySelector('#no-tools').classList.toggle('hidden', visible !== 0);
    });
}

const form = document.querySelector('#tool-form');
if (form) {
    const input = form.querySelector('#images');
    const drop = form.querySelector('#drop-zone');
    const fileList = form.querySelector('#file-list');
    const before = form.querySelector('#before-preview');
    const previews = form.querySelector('#preview-wrap');
    const after = form.querySelector('#after-preview');
    const placeholder = form.querySelector('#after-placeholder');
    const errors = form.querySelector('#form-errors');
    const button = form.querySelector('#submit-button');
    let previewUrl;

    const showFiles = () => {
        if (!input.files.length) return;
        fileList.innerHTML = [...input.files].map((file) => `<div class="file-pill"><span>✓</span><span class="min-w-0 flex-1 truncate">${escapeHtml(file.name)}</span><small>${formatBytes(file.size)}</small></div>`).join('');
        fileList.classList.remove('hidden');
        URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(input.files[0]);
        before.src = previewUrl;
        previews.classList.remove('hidden');
    };
    drop.addEventListener('click', () => input.click());
    drop.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); input.click(); }
    });
    ['dragenter', 'dragover'].forEach((name) => drop.addEventListener(name, (event) => { event.preventDefault(); drop.classList.add('is-dragging'); }));
    ['dragleave', 'drop'].forEach((name) => drop.addEventListener(name, (event) => { event.preventDefault(); drop.classList.remove('is-dragging'); }));
    drop.addEventListener('drop', (event) => {
        const transfer = new DataTransfer();
        [...event.dataTransfer.files].slice(0, form.dataset.multiple === 'true' ? 10 : 1).forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        showFiles();
    });
    input.addEventListener('change', showFiles);

    form.querySelectorAll('input[type="range"]').forEach((range) => range.addEventListener('input', () => {
        const output = form.querySelector(`output[data-for="${range.name}"]`);
        if (output) output.value = range.value;
    }));
    const preset = form.querySelector('#photo-preset');
    preset?.addEventListener('change', () => {
        if (preset.value === 'custom') return;
        const [width, height] = preset.value.split(',');
        form.querySelector('#photo-width').value = width;
        form.querySelector('#photo-height').value = height;
    });

    before?.addEventListener('click', (event) => {
        const picker = form.querySelector('#background-color');
        if (!picker) return;
        const canvas = document.createElement('canvas');
        canvas.width = before.naturalWidth; canvas.height = before.naturalHeight;
        const context = canvas.getContext('2d'); context.drawImage(before, 0, 0);
        const rect = before.getBoundingClientRect();
        const x = Math.floor((event.clientX - rect.left) / rect.width * canvas.width);
        const y = Math.floor((event.clientY - rect.top) / rect.height * canvas.height);
        const [r, g, b] = context.getImageData(x, y, 1, 1).data;
        picker.value = `#${[r, g, b].map((value) => value.toString(16).padStart(2, '0')).join('')}`;
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        errors.classList.add('hidden');
        if (!input.files.length) { showError('Please choose at least one image.'); return; }
        setStatus('processing', 'Uploading securely…', 'Your image is being validated and queued.');
        setBusy(true);
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'Upload failed.');
            await poll(payload.status_url);
        } catch (error) {
            showError(error.message);
            setStatus('failed', 'Something went wrong', error.message);
            setBusy(false);
        }
    });

    async function poll(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Could not check processing status.');
        const task = await response.json();
        if (task.status === 'completed') { showResult(task.result); setBusy(false); return; }
        if (task.status === 'failed') throw new Error(task.error?.message || 'Processing failed.');
        setStatus('processing', task.status === 'pending' ? 'Waiting for a worker…' : 'Working on your image…', 'This page will update automatically.');
        window.setTimeout(() => poll(url).catch((error) => { showError(error.message); setStatus('failed', 'Processing failed', error.message); setBusy(false); }), 1200);
    }

    function showResult(result) {
        setStatus('success', 'Your result is ready', 'Download it now — the link and files expire shortly.');
        const files = result.files || [];
        const image = files.find((file) => !file.name.endsWith('.zip'));
        if (image) { after.src = image.download_url; after.classList.remove('hidden'); placeholder.classList.add('hidden'); }
        const stats = document.querySelector('#result-stats');
        stats.innerHTML = `<div class="stat"><span>Original</span><strong>${formatBytes(result.original_size)}</strong></div><div class="stat"><span>Result</span><strong>${formatBytes(result.output_size)}</strong></div>`;
        stats.classList.remove('hidden');
        document.querySelector('#downloads').innerHTML = files.map((file) => `<a class="download-button" href="${file.download_url}"><span>↓</span><span class="min-w-0"><strong>${escapeHtml(file.label || 'Download')}</strong><small>${escapeHtml(file.name)} · ${formatBytes(file.size)}</small></span></a>`).join('');
    }

    function setStatus(state, title, message) {
        const dot = document.querySelector('#status-dot');
        dot.className = `status-dot ${state}`;
        document.querySelector('#status-title').textContent = title;
        document.querySelector('#status-message').textContent = message;
        document.querySelector('#progress').classList.toggle('hidden', state !== 'processing');
    }
    function showError(message) { errors.textContent = message; errors.classList.remove('hidden'); errors.focus(); }
    function setBusy(busy) { button.disabled = busy; button.querySelector('span').textContent = busy ? 'Processing…' : 'Process image'; }
}

function formatBytes(bytes = 0) {
    if (bytes < 1024) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB']; let value = bytes / 1024; let unit = 0;
    while (value >= 1024 && unit < units.length - 1) { value /= 1024; unit++; }
    return `${value.toFixed(value >= 10 ? 1 : 2)} ${units[unit]}`;
}
function escapeHtml(value) {
    const node = document.createElement('div'); node.textContent = value; return node.innerHTML;
}

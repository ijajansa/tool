const form = document.querySelector('#phase2-form');

if (form) {
    const errors = document.querySelector('#phase2-errors');
    const status = document.querySelector('#phase2-status');
    const button = document.querySelector('#phase2-submit');

    document.querySelector('#add-item')?.addEventListener('click', () => {
        const row = document.querySelector('.item-row').cloneNode(true);
        row.querySelectorAll('input').forEach((input) => { if (!['quantity', 'gst_rate'].includes(input.dataset.item)) input.value = input.dataset.item === 'price' || input.dataset.item === 'discount' || input.dataset.item === 'tax' ? 0 : ''; });
        document.querySelector('#line-items').append(row);
    });
    document.querySelector('#add-section')?.addEventListener('click', () => {
        const row = document.querySelector('.section-row').cloneNode(true);
        row.querySelectorAll('input,textarea').forEach((input) => input.value = '');
        document.querySelector('#resume-sections').append(row);
    });

    if (form.dataset.draft && !['pdf-toolkit', 'bulk-watermark-tool', 'seo-audit', 'qr-code-generator'].includes(form.dataset.tool)) {
        const saved = localStorage.getItem(form.dataset.draft);
        if (saved) {
            try { applyPayload(JSON.parse(saved)); } catch (_) {}
        }
        form.addEventListener('input', () => localStorage.setItem(form.dataset.draft, JSON.stringify(payload())));
    }
    document.querySelector('#generic-draft-export')?.addEventListener('click', () => downloadJson(`${form.dataset.tool}-draft.json`, payload()));
    document.querySelector('#generic-draft-import')?.addEventListener('change', async (event) => {
        try { applyPayload(JSON.parse(await event.target.files[0].text())); } catch (_) { showError('The selected draft is not valid JSON.'); }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        errors?.classList.add('hidden');
        setBusy(true);
        try {
            if (form.dataset.tool === 'qr-code-generator') { await generateQr(); setBusy(false); return; }
            const body = new FormData(form);
            body.set('payload', JSON.stringify(payload()));
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Could not create the result.');
            setStatus('Processing…', 'Your queued task is running.');
            await poll(result.status_url);
        } catch (error) { showError(error.message); setBusy(false); }
    });

    function payload() {
        const data = {};
        form.querySelectorAll('[data-payload]').forEach((input) => { data[input.dataset.payload] = input.type === 'checkbox' ? input.checked : input.value; });
        const items = [...form.querySelectorAll('.item-row')].map((row) => Object.fromEntries([...row.querySelectorAll('[data-item]')].map((input) => [input.dataset.item, input.value])));
        if (items.length) data.items = items;
        const sections = [...form.querySelectorAll('.section-row')].map((row) => Object.fromEntries([...row.querySelectorAll('[data-section]')].map((input) => [input.dataset.section, input.value])));
        if (sections.length) data.sections = sections;
        return data;
    }
    function applyPayload(values) {
        form.querySelectorAll('[data-payload]').forEach((input) => { if (values[input.dataset.payload] !== undefined) input.type === 'checkbox' ? input.checked = Boolean(values[input.dataset.payload]) : input.value = values[input.dataset.payload]; });
        if (Array.isArray(values.items) && document.querySelector('.item-row')) {
            const list = document.querySelector('#line-items'); const template = document.querySelector('.item-row').cloneNode(true); list.innerHTML = '';
            values.items.forEach((item) => { const row = template.cloneNode(true); row.querySelectorAll('[data-item]').forEach((input) => input.value = item[input.dataset.item] ?? ''); list.append(row); });
        }
        if (Array.isArray(values.sections) && document.querySelector('.section-row')) {
            const list = document.querySelector('#resume-sections'); const template = document.querySelector('.section-row').cloneNode(true); list.innerHTML = '';
            values.sections.forEach((section) => { const row = template.cloneNode(true); row.querySelectorAll('[data-section]').forEach((input) => input.value = section[input.dataset.section] ?? ''); list.append(row); });
        }
    }
    async function poll(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const task = await response.json();
        if (task.status === 'failed') throw new Error(task.error.message);
        if (task.status !== 'completed') { setTimeout(() => poll(url).catch((error) => { showError(error.message); setBusy(false); }), 1200); return; }
        setStatus('Result ready', 'Download it before the temporary link expires.');
        document.querySelector('#phase2-downloads').innerHTML = task.result.files.map((file) => `<a class="download-button" href="${file.download_url}"><span>↓</span><span><strong>${escapeHtml(file.label || 'Download result')}</strong><small>${escapeHtml(file.name)}</small></span></a>`).join('');
        setBusy(false);
    }
    async function generateQr() {
        const mode = form.querySelector('input[name="qr_mode"]:checked').value;
        const type = document.querySelector('#qr-type').value;
        let content = document.querySelector('#qr-content').value.trim();
        if (type === 'email' && !content.startsWith('mailto:')) content = `mailto:${content}`;
        if (type === 'phone' && !content.startsWith('tel:')) content = `tel:${content}`;
        if (type === 'wifi' && !content.startsWith('WIFI:')) { const [ssid,password=''] = content.split(';'); content = `WIFI:T:WPA;S:${ssid.replace(/[;,:]/g,'')};P:${password.replace(/[;,:]/g,'')};;`; }
        const response = await fetch('/qr/dynamic', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ mode, content_type: type, content, ecc: document.querySelector('#qr-ecc').value, size: Number(document.querySelector('#qr-size').value), color: document.querySelector('#qr-color').value, 'cf-turnstile-response': form.querySelector('[name="cf-turnstile-response"]')?.value }) });
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message);
        const box = document.querySelector('#qr-result');
        if (result.svg) {
            const blob = new Blob([result.svg], { type: 'image/svg+xml' }); const url = URL.createObjectURL(blob);
            box.innerHTML = `<div class="qr-preview">${result.svg}</div><a class="download-button mt-3" download="qr-code.svg" href="${url}">Download static QR</a>`;
        } else {
            box.innerHTML = `<p class="text-sm"><strong>Save this management token now:</strong><br><code class="break-all">${escapeHtml(result.management_token)}</code></p><a class="download-button mt-3" href="${result.download_url}">Download dynamic QR</a><a class="secondary-button mt-2" target="_blank" href="${result.analytics_url}">View analytics JSON</a>`;
        }
        setStatus('QR code ready', mode === 'dynamic' ? 'Save the management token; it cannot be recovered.' : 'Content is encoded directly with no tracking.');
    }
    function showError(message) { if (errors) { errors.textContent = message; errors.classList.remove('hidden'); } setStatus('Could not create result', message); }
    function setStatus(title, message) { status.querySelector('h2').textContent = title; status.querySelector('p').textContent = message; }
    function setBusy(busy) { if (button) { button.disabled = busy; button.querySelector('span').textContent = busy ? 'Working…' : (form.dataset.tool === 'qr-code-generator' ? 'Generate QR code' : 'Create result'); } }
}

const canvas = document.querySelector('#poster-canvas');
if (canvas) {
    const editor = new Canvas(canvas, { preserveObjectStacking: true });
    const headline = new Textbox(document.querySelector('#poster-text').value, { left: 120, top: 260, width: 840, fontSize: 72, fontWeight: 'bold', textAlign: 'center', fill: document.querySelector('#poster-color').value });
    editor.add(headline); editor.setActiveObject(headline);
    const background = () => { const first=document.querySelector('#poster-bg').value;const second=document.querySelector('#poster-bg-two').value;editor.backgroundColor=document.querySelector('#poster-bg-mode').value==='gradient'?new Gradient({type:'linear',gradientUnits:'pixels',coords:{x1:0,y1:0,x2:editor.width,y2:editor.height},colorStops:[{offset:0,color:first},{offset:1,color:second}]}):first;editor.requestRenderAll(); };
    const resize = () => { const [w,h] = document.querySelector('#poster-preset').value.split(',').map(Number); editor.setDimensions({width:w,height:h});background(); };
    document.querySelector('#poster-preset').addEventListener('change', resize);
    document.querySelectorAll('#poster-bg,#poster-bg-two,#poster-bg-mode').forEach((input)=>input.addEventListener('input',background));
    document.querySelector('#poster-text').addEventListener('input',(event)=>{headline.set('text',event.target.value);editor.requestRenderAll();});
    document.querySelector('#poster-color').addEventListener('input',(event)=>{headline.set('fill',event.target.value);editor.requestRenderAll();});
    document.querySelector('#poster-image').addEventListener('change',async(event)=>{if(!event.target.files[0])return;const image=await FabricImage.fromURL(URL.createObjectURL(event.target.files[0]));image.scaleToWidth(editor.width*.6);image.set({left:editor.width*.2,top:editor.height*.15});editor.add(image);editor.setActiveObject(image);});
    document.querySelector('#poster-download').addEventListener('click',()=>{const format=document.querySelector('#poster-format').value;const link=document.createElement('a');link.download=`social-poster.${format==='jpeg'?'jpg':format}`;link.href=editor.toDataURL({format,multiplier:1,quality:.92});link.click();});
    document.querySelector('#poster-front').addEventListener('click',()=>{const object=editor.getActiveObject();if(object){editor.bringObjectToFront(object);editor.requestRenderAll();}});
    document.querySelector('#poster-back').addEventListener('click',()=>{const object=editor.getActiveObject();if(object){editor.sendObjectToBack(object);editor.requestRenderAll();}});
    document.querySelector('#poster-align').addEventListener('click',()=>{const object=editor.getActiveObject();if(object){object.set({left:(editor.width-object.getScaledWidth())/2});object.setCoords();editor.requestRenderAll();}});
    document.querySelector('#draft-export').addEventListener('click',()=>downloadJson('poster-draft.json',editor.toJSON()));
    resize();
}

function escapeHtml(value = '') { const node = document.createElement('div'); node.textContent = value; return node.innerHTML; }
function downloadJson(name, data) { const blob = new Blob([JSON.stringify(data, null, 2)], {type:'application/json'}); const link = document.createElement('a'); link.download = name; link.href = URL.createObjectURL(blob); link.click(); }
import { Canvas, FabricImage, Gradient, Textbox } from 'fabric';

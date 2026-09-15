(() => {
    const THEME_KEY   = 'gifConverterTheme';
    const PRESET_KEY  = 'gifConverterPresets';
    const HISTORY_KEY = 'gifConverterHistory';
    const MAX_HISTORY = 20;

    /* ── DOM refs ───────────────────────────────────────────────────── */
    const dropzone        = document.getElementById('dropzone');
    const videoInput      = document.getElementById('videoInput');
    const dropzoneLabel   = document.getElementById('dropzoneLabel');
    const videoInfo       = document.getElementById('videoInfo');
    const infoName        = document.getElementById('infoName');
    const infoDuration    = document.getElementById('infoDuration');
    const infoDims        = document.getElementById('infoDims');
    const infoFps         = document.getElementById('infoFps');
    const uploadPanel     = document.getElementById('uploadPanel');
    const uploadLabel     = document.getElementById('uploadLabel');
    const uploadPercent   = document.getElementById('uploadPercent');
    const uploadFill      = document.getElementById('uploadFill');
    const uploadTrack     = document.getElementById('uploadTrack');
    const settingsPanel   = document.getElementById('settingsPanel');
    const convertForm     = document.getElementById('convertForm');
    const jobIdInput      = document.getElementById('jobId');
    const trimStart       = document.getElementById('trimStart');
    const trimEnd         = document.getElementById('trimEnd');
    const trimStartLabel  = document.getElementById('trimStartLabel');
    const trimEndLabel    = document.getElementById('trimEndLabel');
    const trimDurLabel    = document.getElementById('trimDurLabel');
    const trimRangeSel    = document.getElementById('trimRangeSelected');
    const startTimeInput  = document.getElementById('startTimeInput');
    const endTimeInput    = document.getElementById('endTimeInput');
    const fpsInput        = document.getElementById('fpsInput');
    const widthInput      = document.getElementById('widthInput');
    const presetInput     = document.getElementById('presetInput');
    const cropInput       = document.getElementById('cropInput');
    const captionInput    = document.getElementById('captionInput');
    const captionPosInput = document.getElementById('captionPosInput');
    const submitBtn       = document.getElementById('submitBtn');
    const addQueueBtn     = document.getElementById('addQueueBtn');
    const status          = document.getElementById('status');
    const progressPanel   = document.getElementById('progressPanel');
    const progressLabel   = document.getElementById('progressLabel');
    const progressPercent = document.getElementById('progressPercent');
    const progressFill    = document.getElementById('progressFill');
    const convertTrack    = document.getElementById('convertTrack');
    const result          = document.getElementById('result');
    const resultMeta      = document.getElementById('resultMeta');
    const gifPreview      = document.getElementById('gifPreview');
    const downloadLink    = document.getElementById('downloadLink');
    const convertAnotherBtn = document.getElementById('convertAnotherBtn');
    const queueCount      = document.getElementById('queueCount');
    const queueList       = document.getElementById('queueList');
    const queueActions    = document.getElementById('queueActions');
    const processQueueBtn = document.getElementById('processQueueBtn');
    const clearQueueBtn   = document.getElementById('clearQueueBtn');
    const downloadAllBtn  = document.getElementById('downloadAllBtn');
    const presetNameInput = document.getElementById('presetNameInput');
    const savePresetBtn   = document.getElementById('savePresetBtn');
    const presetList      = document.getElementById('presetList');
    const historyList     = document.getElementById('historyList');
    const historyEmpty    = document.getElementById('historyEmpty');
    const themeToggle     = document.getElementById('themeToggle');
    const darkPreferenceQuery = window.matchMedia('(prefers-color-scheme: dark)');

    /* ── State ──────────────────────────────────────────────────────── */
    let currentJobId   = null;
    let currentDuration = 0;
    let pollTimer      = null;
    let queue          = [];
    let uploadBusy     = false;

    /* ── Helpers ────────────────────────────────────────────────────── */
    const formatBytes = (b) => {
        if (b < 1024) return `${b} B`;
        if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
        return `${(b / (1024 * 1024)).toFixed(2)} MB`;
    };

    const getDownloadName = (data, fallback = 'converted.gif') => {
        return data && typeof data.downloadName === 'string' && data.downloadName.trim() !== ''
            ? data.downloadName.trim()
            : fallback;
    };

    /** Fetch as blob and trigger a real save-as dialog — works on all mobile browsers */
    const triggerBlobDownload = async (url, filename) => {
        try {
            const resp = await fetch(url);
            if (!resp.ok) throw new Error('Download failed');
            const blob = await resp.blob();
            const blobUrl = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = filename || 'converted.gif';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            // Revoke after a short delay so the browser has time to start the download
            setTimeout(() => URL.revokeObjectURL(blobUrl), 5000);
        } catch (err) {
            // Fallback: open the URL directly
            window.open(url, '_blank');
        }
    };

    const setStatus = (msg) => { status.textContent = msg; };

    const refreshActionState = () => {
        const hasSingleSelection = Boolean(currentJobId);
        const hasQueueItems = queue.length > 0;

        settingsPanel.classList.toggle('hidden', !hasSingleSelection && !hasQueueItems);

        if (hasSingleSelection) {
            submitBtn.textContent = 'Convert to GIF';
            submitBtn.disabled = uploadBusy;
            addQueueBtn.disabled = uploadBusy;
        } else if (hasQueueItems) {
            submitBtn.textContent = 'Convert Queue';
            submitBtn.disabled = uploadBusy;
            addQueueBtn.disabled = true;
        } else {
            submitBtn.textContent = 'Convert to GIF';
            submitBtn.disabled = true;
            addQueueBtn.disabled = true;
        }

        processQueueBtn.disabled = uploadBusy || !hasQueueItems;
        clearQueueBtn.disabled = uploadBusy || !hasQueueItems;
    };

    const setUploadBusy = (isBusy) => {
        uploadBusy = isBusy;
        refreshActionState();
    };

    /* ── Upload progress bar ────────────────────────────────────────── */
    const setUploadProgress = (pct, label) => {
        const v = Math.max(0, Math.min(100, Math.round(pct)));
        uploadFill.style.width = `${v}%`;
        uploadPercent.textContent = `${v}%`;
        uploadTrack.setAttribute('aria-valuenow', String(v));
        if (label) uploadLabel.textContent = label;
    };

    /* ── Conversion progress bar ────────────────────────────────────── */
    const setConvertProgress = (pct, label) => {
        const v = Math.max(0, Math.min(100, Math.round(pct)));
        progressFill.style.width = `${v}%`;
        progressPercent.textContent = `${v}%`;
        convertTrack.setAttribute('aria-valuenow', String(v));
        if (label) progressLabel.textContent = label;
    };

    const setFinalizing = () => {
        convertTrack.classList.add('is-indeterminate');
        progressPercent.textContent = 'Finalizing';
        progressLabel.textContent = 'Finishing up...';
    };

    const finishConvertProgress = () => {
        convertTrack.classList.remove('is-indeterminate');
        setConvertProgress(100, 'Completed');
        window.setTimeout(() => {
            progressPanel.classList.add('hidden');
            setConvertProgress(0, 'Converting...');
        }, 600);
    };

    /* ── Theme ──────────────────────────────────────────────────────── */
    const getStoredThemeMode = () => {
        const s = localStorage.getItem(THEME_KEY);
        return (s === 'light' || s === 'dark' || s === 'auto') ? s : 'auto';
    };
    const getAppliedTheme = (mode) => {
        if (mode === 'dark') return 'dark';
        if (mode === 'light') return 'light';
        return darkPreferenceQuery.matches ? 'dark' : 'light';
    };
    const applyTheme = (mode) => {
        const applied = getAppliedTheme(mode);
        document.documentElement.setAttribute('data-theme', applied);
        themeToggle.textContent = mode === 'auto'
            ? `Auto (${applied})` : mode[0].toUpperCase() + mode.slice(1);
    };
    const cycleTheme = (m) => m === 'auto' ? 'dark' : m === 'dark' ? 'light' : 'auto';

    let themeMode = getStoredThemeMode();
    applyTheme(themeMode);
    themeToggle.addEventListener('click', () => {
        themeMode = cycleTheme(themeMode);
        localStorage.setItem(THEME_KEY, themeMode);
        applyTheme(themeMode);
    });
    darkPreferenceQuery.addEventListener('change', () => {
        if (themeMode === 'auto') applyTheme(themeMode);
    });

    /* ── Trim sliders ───────────────────────────────────────────────── */
    const updateTrimUI = () => {
        let s = parseFloat(trimStart.value);
        let e = parseFloat(trimEnd.value);
        const gap = 0.5;
        if (s > e - gap) {
            if (document.activeElement === trimStart) {
                trimStart.value = String(Math.max(0, e - gap));
                s = parseFloat(trimStart.value);
            } else {
                trimEnd.value = String(Math.min(100, s + gap));
                e = parseFloat(trimEnd.value);
            }
        }
        const sPct = s;
        const ePct = e;
        trimRangeSel.style.left  = `${sPct}%`;
        trimRangeSel.style.width = `${ePct - sPct}%`;

        const sTime = (s / 100) * currentDuration;
        const eTime = (e / 100) * currentDuration;
        trimStartLabel.textContent = `${sTime.toFixed(1)}s`;
        trimEndLabel.textContent   = `${eTime.toFixed(1)}s`;
        trimDurLabel.textContent   = `${(eTime - sTime).toFixed(1)}s`;
        startTimeInput.value = String(sTime.toFixed(3));
        endTimeInput.value   = String(eTime.toFixed(3));
    };

    trimStart.addEventListener('input', updateTrimUI);
    trimEnd.addEventListener('input', updateTrimUI);

    const resetTrim = (duration) => {
        currentDuration = duration;
        trimStart.value = '0';
        trimEnd.value   = '100';
        updateTrimUI();
    };

    /* ── Dropzone ───────────────────────────────────────────────────── */
    const openFilePicker = () => videoInput.click();

    const showProbedFile = (data) => {
        currentJobId = data.jobId;
        jobIdInput.value = data.jobId;

        infoName.textContent = data.fileName;
        infoDuration.textContent = `${parseFloat(data.duration).toFixed(2)}s`;
        infoDims.textContent = data.width && data.height ? `${data.width}x${data.height}` : '';
        infoFps.textContent = data.fps ? `${data.fps} fps` : '';
        videoInfo.classList.remove('hidden');

        resetTrim(parseFloat(data.duration));
        refreshActionState();
    };

    const showBulkSelection = () => {
        infoName.textContent = `${queue.length} file${queue.length === 1 ? '' : 's'} queued`;
        infoDuration.textContent = 'Bulk mode';
        infoDims.textContent = 'Current settings will be applied to all';
        infoFps.textContent = '';
        videoInfo.classList.remove('hidden');
        refreshActionState();
    };

    const probeFile = (file, uploadMessage) => new Promise((resolve, reject) => {
        const fd = new FormData();
        fd.append('action', 'probe');
        fd.append('video', file);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api.php');
        xhr.responseType = 'json';
        xhr.timeout = 5 * 60 * 1000;

        uploadPanel.classList.remove('hidden');
        setUploadProgress(0, uploadMessage);

        xhr.upload.addEventListener('progress', (ev) => {
            if (ev.lengthComputable) {
                setUploadProgress((ev.loaded / ev.total) * 100, uploadMessage);
            }
        });

        xhr.addEventListener('load', () => {
            uploadPanel.classList.add('hidden');
            const payload = xhr.response;
            if (xhr.status < 200 || xhr.status >= 300 || !payload || !payload.ok) {
                const msg = payload && payload.error ? payload.error.message : 'Upload failed.';
                reject(new Error(msg));
                return;
            }

            resolve(payload.data);
        });

        xhr.addEventListener('error', () => {
            uploadPanel.classList.add('hidden');
            reject(new Error('Network error during upload.'));
        });

        xhr.addEventListener('timeout', () => {
            uploadPanel.classList.add('hidden');
            reject(new Error('Upload timed out.'));
        });

        xhr.send(fd);
    });

    const getSettingsSnapshot = () => ({
        fps: parseInt(fpsInput.value, 10) || 10,
        width: parseInt(widthInput.value, 10) || 480,
        preset: presetInput.value || 'balanced',
        startTime: parseFloat(startTimeInput.value) || 0,
        endTime: parseFloat(endTimeInput.value) || 0,
        crop: cropInput.value,
        caption: captionInput.value,
        captionPos: captionPosInput.value,
    });

    const queueJobFromProbe = (probeData, settings) => {
        queue.push({
            jobId: probeData.jobId,
            settings: { ...settings },
            fileName: probeData.fileName,
            status: 'waiting',
            downloadUrl: null,
        });
    };

    const handleFilesSelected = async (files) => {
        const selectedFiles = Array.from(files || []).filter(Boolean);
        if (selectedFiles.length === 0) {
            return;
        }

        result.classList.add('hidden');
        gifPreview.removeAttribute('src');

        if (selectedFiles.length === 1) {
            const file = selectedFiles[0];
            settingsPanel.classList.add('hidden');
            videoInfo.classList.add('hidden');
            currentJobId = null;
            jobIdInput.value = '';
            setUploadBusy(true);
            setStatus('Uploading...');

            try {
                const data = await probeFile(file, 'Uploading...');
                showProbedFile(data);
                setStatus('Ready. Adjust settings and convert.');
            } catch (err) {
                setStatus(err.message || 'Upload failed.');
            } finally {
                setUploadBusy(false);
            }
            return;
        }

        const settingsSnapshot = getSettingsSnapshot();
        let addedCount = 0;
    currentJobId = null;
    jobIdInput.value = '';
        setUploadBusy(true);
        setStatus(`Adding ${selectedFiles.length} files to queue...`);

        for (let index = 0; index < selectedFiles.length; index += 1) {
            const file = selectedFiles[index];
            try {
                const data = await probeFile(file, `Uploading ${index + 1} of ${selectedFiles.length}...`);
                queueJobFromProbe(data, settingsSnapshot);
                addedCount += 1;
            } catch (err) {
                setStatus(`Skipped ${file.name}: ${err.message || 'Upload failed.'}`);
            }
        }

        renderQueue();
        if (queue.length > 0) {
            showBulkSelection();
        }
        setUploadBusy(false);

        if (addedCount > 0) {
            setStatus(`Added ${addedCount} file${addedCount === 1 ? '' : 's'} to the queue with the current settings.`);
        } else {
            setStatus('No files were added to the queue.');
        }
    };

    dropzone.addEventListener('click', openFilePicker);
    dropzone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openFilePicker(); }
    });

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('drag-over');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        handleFilesSelected(e.dataTransfer.files);
    });

    videoInput.addEventListener('change', () => {
        if (videoInput.files && videoInput.files.length > 0) {
            handleFilesSelected(videoInput.files);
            videoInput.value = '';
        }
    });

    /* ── Poll progress ──────────────────────────────────────────────── */
    const startPolling = (jobId) => {
        let lastPct = 0;
        pollTimer = window.setInterval(async () => {
            try {
                const r = await fetch(`api.php?action=progress&job=${encodeURIComponent(jobId)}`);
                if (!r.ok) return;
                const d = await r.json();
                const pct = d.data ? d.data.pct : 0;
                if (pct > lastPct) {
                    lastPct = pct;
                    if (pct >= 99) {
                        setFinalizing();
                    } else {
                        setConvertProgress(pct, 'Converting with ffmpeg...');
                    }
                }
            } catch (_) { /* ignore */ }
        }, 700);
    };

    const stopPolling = () => {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    };

    /* ── Convert ────────────────────────────────────────────────────── */
    const runConvert = async (jobId, settings) => {
        setStatus('Converting...');
        progressPanel.classList.remove('hidden');
        convertTrack.classList.remove('is-indeterminate');
        setConvertProgress(0, 'Starting...');
        submitBtn.disabled  = true;
        addQueueBtn.disabled = true;

        startPolling(jobId);

        try {
            const fd = new FormData();
            fd.append('action',      'convert');
            fd.append('job_id',      jobId);
            fd.append('fps',         String(settings.fps));
            fd.append('width',       String(settings.width));
            fd.append('preset',      settings.preset);
            fd.append('start_time',  String(settings.startTime));
            fd.append('end_time',    String(settings.endTime));
            fd.append('crop',        settings.crop);
            fd.append('caption',     settings.caption);
            fd.append('caption_pos', settings.captionPos);

            const r = await fetch('api.php', { method: 'POST', body: fd });
            const payload = await r.json();
            if (!r.ok || !payload.ok) {
                throw new Error(payload.error ? payload.error.message : 'Conversion failed.');
            }
            return payload.data;
        } finally {
            stopPolling();
        }
    };

    const getCurrentSettings = () => getSettingsSnapshot();

    const showResult = (data) => {
        finishConvertProgress();
        const dlName = getDownloadName(data);
        downloadLink.href  = data.downloadUrl;
        downloadLink.download = dlName;
        downloadLink.onclick = (e) => { e.preventDefault(); triggerBlobDownload(data.downloadUrl, dlName); };
        gifPreview.src     = data.previewUrl + '&t=' + Date.now();
        resultMeta.textContent = `GIF: ${formatBytes(data.gifBytes)} | Input: ${data.inputDuration.toFixed(1)}s | Took: ${(data.elapsedMs / 1000).toFixed(2)}s`;
        result.classList.remove('hidden');
        addToHistory(data);
        setStatus('Conversion complete.');
    };

    convertForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!currentJobId && queue.length > 0) {
            await processQueue();
            return;
        }
        if (!currentJobId) { setStatus('No file probed.'); return; }
        try {
            const data = await runConvert(currentJobId, getCurrentSettings());
            showResult(data);
        } catch (err) {
            progressPanel.classList.add('hidden');
            convertTrack.classList.remove('is-indeterminate');
            setStatus(err.message || 'Unexpected error.');
        } finally {
            submitBtn.disabled  = false;
            addQueueBtn.disabled = false;
        }
    });

    convertAnotherBtn.addEventListener('click', () => {
        result.classList.add('hidden');
        gifPreview.removeAttribute('src');
        currentJobId = null;
        jobIdInput.value = '';
        if (queue.length > 0) {
            showBulkSelection();
        } else {
            settingsPanel.classList.add('hidden');
            videoInfo.classList.add('hidden');
        }
        refreshActionState();
        setStatus('Choose a video to begin.');
    });

    /* ── Batch queue ────────────────────────────────────────────────── */
    const renderQueue = () => {
        queueCount.textContent = String(queue.length);
        queueList.innerHTML = '';
        if (queue.length === 0) {
            queueActions.style.display = 'none';
            downloadAllBtn.style.display = 'none';
            refreshActionState();
            return;
        }
        queueActions.style.display = '';
        downloadAllBtn.style.display = queue.some(i => i.status === 'done') ? '' : 'none';
        queue.forEach((item, idx) => {
            const li = document.createElement('li');
            li.className = 'queueItem';
            li.dataset.idx = String(idx);
            if (item.previewUrl) {
                const thumb = document.createElement('img');
                thumb.className = 'queueThumb';
                thumb.src = item.previewUrl;
                thumb.alt = `${item.fileName || `Job ${idx + 1}`} preview`;
                li.append(thumb);
            }
            const pill = document.createElement('span');
            pill.className = `statusPill status-${item.status}`;
            pill.textContent = item.status;
            const name = document.createElement('span');
            name.className = 'queueName';
            name.textContent = item.fileName || `Job ${idx + 1}`;
            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'btnTiny';
            del.textContent = 'X';
            del.addEventListener('click', () => {
                queue.splice(idx, 1);
                renderQueue();
            });
            li.append(pill, name, del);
            if (item.downloadUrl) {
                const dl = document.createElement('a');
                const dlName = item.downloadName || 'converted.gif';
                dl.href = item.downloadUrl;
                dl.download = dlName;
                dl.className = 'btnTiny';
                dl.textContent = 'DL';
                dl.addEventListener('click', (ev) => { ev.preventDefault(); triggerBlobDownload(item.downloadUrl, dlName); });
                li.append(dl);
            }
            queueList.appendChild(li);
        });
        refreshActionState();
    };

    addQueueBtn.addEventListener('click', () => {
        if (!currentJobId) return;
        queueJobFromProbe({ jobId: currentJobId, fileName: infoName.textContent }, getCurrentSettings());
        renderQueue();
        setStatus('Added to queue.');
    });

    clearQueueBtn.addEventListener('click', () => {
        queue = [];
        renderQueue();
        if (!currentJobId) {
            videoInfo.classList.add('hidden');
        }
        setUploadBusy(false);
    });

    const processQueue = async () => {
        processQueueBtn.disabled = true;
        submitBtn.disabled = true;
        for (let i = 0; i < queue.length; i++) {
            const item = queue[i];
            if (item.status === 'done') continue;
            item.status = 'converting';
            renderQueue();
            try {
                const data = await runConvert(item.jobId, item.settings);
                item.status = 'done';
                item.downloadUrl = data.downloadUrl;
                item.previewUrl = data.previewUrl + '&t=' + Date.now();
                item.downloadName = getDownloadName(data, item.fileName ? `${item.fileName}.gif` : 'converted.gif');
                showResult(data);
            } catch (err) {
                item.status = 'error';
                setStatus(`Queue item ${i + 1} failed: ${err.message}`);
            }
            renderQueue();
        }
        processQueueBtn.disabled = false;
        renderQueue();
    };

    processQueueBtn.addEventListener('click', processQueue);

    downloadAllBtn.addEventListener('click', async () => {
        const done = queue.filter(i => i.status === 'done' && i.downloadUrl);
        if (done.length === 0) return;
        
        downloadAllBtn.disabled = true;
        setStatus('Preparing downloads...');
        
        try {
            const zip = new JSZip();
            
            for (let i = 0; i < done.length; i++) {
                const item = done[i];
                try {
                    const resp = await fetch(item.downloadUrl);
                    if (!resp.ok) throw new Error('Download failed');
                    const blob = await resp.blob();
                    const filename = item.downloadName || `converted_${i + 1}.gif`;
                    zip.file(filename, blob);
                } catch (err) {
                    console.error(`Failed to download ${item.downloadName}:`, err);
                }
            }
            
            setStatus('Creating ZIP...');
            const zipBlob = await zip.generateAsync({ type: 'blob' });
            const zipUrl = URL.createObjectURL(zipBlob);
            const a = document.createElement('a');
            a.href = zipUrl;
            a.download = `gifs_${Date.now()}.zip`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(() => URL.revokeObjectURL(zipUrl), 5000);
            
            setStatus(`Downloaded ${done.length} GIF(s) as ZIP.`);
        } catch (err) {
            setStatus(`Error: ${err.message}`);
            console.error('Download all failed:', err);
        } finally {
            downloadAllBtn.disabled = false;
        }
    });

    /* ── Presets ────────────────────────────────────────────────────── */
    const loadPresets = () => {
        try { return JSON.parse(localStorage.getItem(PRESET_KEY) || '[]'); }
        catch (_) { return []; }
    };
    const savePresets = (p) => localStorage.setItem(PRESET_KEY, JSON.stringify(p));

    const renderPresets = () => {
        const presets = loadPresets();
        presetList.innerHTML = '';
        presets.forEach((p, idx) => {
            const li = document.createElement('li');
            li.className = 'presetRow';
            const name = document.createElement('span');
            name.textContent = p.name;
            const load = document.createElement('button');
            load.type = 'button';
            load.className = 'btnTiny';
            load.textContent = 'Load';
            load.addEventListener('click', () => {
                fpsInput.value   = String(p.fps);
                widthInput.value = String(p.width);
                presetInput.value   = p.preset;
                cropInput.value     = p.crop || '';
                captionInput.value  = p.caption || '';
                captionPosInput.value = p.captionPos || 'bottom';
            });
            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'btnTiny';
            del.textContent = 'Del';
            del.addEventListener('click', () => {
                const arr = loadPresets();
                arr.splice(idx, 1);
                savePresets(arr);
                renderPresets();
            });
            li.append(name, load, del);
            presetList.appendChild(li);
        });
    };

    savePresetBtn.addEventListener('click', () => {
        const pname = presetNameInput.value.trim();
        if (!pname) { alert('Enter a preset name.'); return; }
        const arr = loadPresets();
        arr.push({ name: pname, ...getCurrentSettings() });
        savePresets(arr);
        presetNameInput.value = '';
        renderPresets();
    });

    renderPresets();

    /* ── History ────────────────────────────────────────────────────── */
    const loadHistory = () => {
        try { return JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'); }
        catch (_) { return []; }
    };
    const saveHistory = (h) => localStorage.setItem(HISTORY_KEY, JSON.stringify(h));

    const addToHistory = (data) => {
        const h = loadHistory();
        h.unshift({
            ts:          Date.now(),
            gifBytes:    data.gifBytes,
            elapsedMs:   data.elapsedMs,
            downloadUrl: data.downloadUrl,
            downloadName: getDownloadName(data),
            previewUrl:  data.previewUrl,
        });
        if (h.length > MAX_HISTORY) h.length = MAX_HISTORY;
        saveHistory(h);
        renderHistory();
    };

    const renderHistory = () => {
        const h = loadHistory();
        historyList.innerHTML = '';
        if (h.length === 0) {
            historyEmpty.style.display = '';
            return;
        }
        historyEmpty.style.display = 'none';
        h.forEach((item) => {
            const li = document.createElement('li');
            li.className = 'historyRow';
            const date = new Date(item.ts);
            const ts = document.createElement('span');
            ts.className = 'historyTs';
            ts.textContent = `${date.toLocaleDateString()} ${date.toLocaleTimeString()}`;
            const sz = document.createElement('span');
            sz.textContent = formatBytes(item.gifBytes);
            const dl = document.createElement('a');
            const dlName = item.downloadName || 'converted.gif';
            dl.href = item.downloadUrl;
            dl.download = dlName;
            dl.className = 'btnTiny';
            dl.textContent = 'Download';
            dl.addEventListener('click', (ev) => { ev.preventDefault(); triggerBlobDownload(item.downloadUrl, dlName); });
            li.append(ts, sz, dl);
            historyList.appendChild(li);
        });
    };

    renderHistory();
    renderQueue();
    setUploadBusy(false);
})();

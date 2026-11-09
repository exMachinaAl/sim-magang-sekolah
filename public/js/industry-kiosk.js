(() => {
  const root = document.querySelector('[data-kiosk-scanner]');
  if (!root) return;

  const video = root.querySelector('[data-kiosk-video]');
  const startButton = root.querySelector('[data-kiosk-start]');
  const stopButton = root.querySelector('[data-kiosk-stop]');
  const status = root.querySelector('[data-kiosk-status]');
  const confirmBox = root.querySelector('[data-kiosk-confirm]');
  const okButton = root.querySelector('[data-kiosk-confirm-ok]');
  const cancelButton = root.querySelector('[data-kiosk-confirm-cancel]');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const scanPrefix = root.dataset.scanPrefix;
  let stream = null;
  let detector = null;
  let scanning = false;
  let busy = false;
  let current = null;
  let heldToken = null;
  let emptyFrames = 0;

  const setStatus = (message, error = false) => {
    status.textContent = message;
    status.classList.toggle('error', error);
  };

  const request = async (url, body) => {
    const response = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
      body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const fallback = response.status === 403 ? 'Perangkat tidak berhak memvalidasi jurnal ini.'
        : response.status === 409 ? 'Isi jurnal berubah. Minta siswa membuat QR baru.'
        : response.status === 410 ? 'QR sudah kedaluwarsa. Minta siswa membuat QR baru.'
        : response.status === 422 ? 'QR atau data jurnal tidak valid.' : 'QR tidak dapat diproses.';
      throw new Error(data.message || fallback);
    }
    return data;
  };

  const tokenFromValue = raw => {
    if (/^[A-Za-z0-9]{64}$/.test(raw)) return raw;
    try {
      const url = new URL(raw, window.location.href);
      if (url.origin !== window.location.origin || !url.href.startsWith(scanPrefix)) return null;
      const token = url.pathname.split('/').filter(Boolean).pop() || '';
      return /^[A-Za-z0-9]{64}$/.test(token) ? token : null;
    } catch { return null; }
  };

  const addSessionResult = (journal, message, time = '') => {
    root.querySelector('[data-kiosk-session-empty]')?.remove();
    const item = document.createElement('div');
    item.className = 'kiosk-session-item';
    const title = document.createElement('strong');
    title.textContent = `${journal.student_name} · NIS ${journal.nis}`;
    const detail = document.createElement('span');
    detail.textContent = `${message}${time ? ` · ${time} WIB` : ''}`;
    item.append(title, detail);
    root.querySelector('[data-kiosk-session]').append(item);
  };

  const fillConfirmation = data => {
    const journal = data.journal;
    root.querySelector('[data-kiosk-student]').textContent = journal.student_name;
    root.querySelector('[data-kiosk-nis]').textContent = journal.nis;
    root.querySelector('[data-kiosk-date]').textContent = journal.activity_date;
    root.querySelector('[data-kiosk-revision]').textContent = journal.revision;
    root.querySelector('[data-kiosk-score]').textContent = `${journal.industry_score} · ${journal.score_category}`;
    root.querySelector('[data-kiosk-place]').textContent = [journal.starts_time && journal.ends_time ? `${journal.starts_time}–${journal.ends_time}` : '', journal.work_area || ''].filter(Boolean).join(' · ') || '—';
    root.querySelector('[data-kiosk-activity]').textContent = journal.activity;
    root.querySelector('[data-kiosk-notes]').textContent = [journal.equipment_notes && `Peralatan: ${journal.equipment_notes}`, journal.outcome && `Hasil: ${journal.outcome}`, journal.obstacles && `Kendala: ${journal.obstacles}`].filter(Boolean).join(' · ');
    confirmBox.hidden = false;
    okButton.focus();
  };

  const resume = (message = 'Scanner siap untuk QR berikutnya.') => {
    current = null;
    busy = false;
    confirmBox.hidden = true;
    setStatus(message);
  };

  const preview = async token => {
    if (busy) return;
    busy = true;
    setStatus('Memeriksa QR jurnal…');
    try {
      const data = await request(root.dataset.previewUrl, {token});
      if (data.status === 'already_validated') {
        addSessionResult(data.journal, 'Sudah dipindai; tidak dicatat ulang', data.scanned_at || '');
        setStatus(`${data.journal.student_name}: jurnal yang sama sudah dipindai. Scanner dilanjutkan.`);
        window.setTimeout(() => resume(), 1400);
        return;
      }
      current = {token, revision: data.journal.revision, journal: data.journal};
      fillConfirmation(data);
      setStatus('Periksa siswa, kegiatan, dan nilai. Tekan OK untuk mengesahkan atau Batal untuk melewati.');
    } catch (error) {
      setStatus(error.message, true);
      window.setTimeout(() => resume(), 1800);
    }
  };

  const scanLoop = async () => {
    if (!scanning) return;
    try {
      if (!busy && video.readyState >= 2) {
        const codes = await detector.detect(video);
        if (!codes.length) {
          emptyFrames += 1;
          if (emptyFrames > 4) heldToken = null;
        } else {
          emptyFrames = 0;
          const token = tokenFromValue(codes[0].rawValue || '');
          if (!token) setStatus('QR bukan QR jurnal SIMMAG.', true);
          else if (token !== heldToken) { heldToken = token; await preview(token); }
        }
      }
    } catch { setStatus('Kamera gagal membaca QR. Coba atur jarak atau pencahayaan.', true); }
    window.setTimeout(scanLoop, 220);
  };

  const start = async () => {
    if (stream) return;
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || !('BarcodeDetector' in window)) {
      setStatus('Scanner portal tidak didukung browser ini. Gunakan Chrome Android terbaru atau pindai QR melalui Google Camera.', true);
      return;
    }
    try {
      detector = new BarcodeDetector({formats: ['qr_code']});
      stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}, audio: false});
      video.srcObject = stream;
      await video.play();
      scanning = true;
      startButton.hidden = true;
      stopButton.hidden = false;
      setStatus('Scanner aktif. Arahkan QR ke kotak kamera.');
      scanLoop();
    } catch { setStatus('Kamera belum dapat digunakan. Izinkan akses kamera atau gunakan Google Camera.', true); }
  };

  const stop = () => {
    scanning = false;
    stream?.getTracks().forEach(track => track.stop());
    stream = null;
    video.srcObject = null;
    startButton.hidden = false;
    stopButton.hidden = true;
    setStatus('Kamera dimatikan.');
  };

  okButton.addEventListener('click', async () => {
    if (!current) return;
    okButton.disabled = true;
    cancelButton.disabled = true;
    setStatus('Menyimpan validasi…');
    try {
      const data = await request(root.dataset.validateUrl, {token: current.token, revision: current.revision});
      addSessionResult(current.journal, data.status === 'validated' ? 'Berhasil divalidasi' : 'Sudah dipindai', data.scanned_at || '');
      resume(data.message + ' Scanner siap untuk siswa berikutnya.');
    } catch (error) { setStatus(error.message, true); }
    finally { okButton.disabled = false; cancelButton.disabled = false; }
  });
  cancelButton.addEventListener('click', () => resume('Dibatalkan; tidak ada validasi yang disimpan. Scanner siap untuk QR berikutnya.'));
  startButton.addEventListener('click', start);
  stopButton.addEventListener('click', stop);
  window.addEventListener('pagehide', stop);

  const fragment = new URLSearchParams(window.location.hash.slice(1));
  const initialToken = tokenFromValue(fragment.get('scan') || '');
  if (initialToken) {
    history.replaceState(null, '', window.location.pathname + window.location.search);
    preview(initialToken);
    start();
  } else {
    start();
  }
})();

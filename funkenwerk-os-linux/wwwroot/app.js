(() => {
  'use strict';

  // =========================================================================
  // 1. ZERO-LATENCY CASH REGISTER CHIME (order_ching.wav)
  // =========================================================================
  let audioCtx = null;
  let chimeBuffer = null;

  async function initChimeAudio() {
    try {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      const res = await fetch('/order_ching.wav');
      const arr = await res.arrayBuffer();
      chimeBuffer = await audioCtx.decodeAudioData(arr);
    } catch { }
  }

  function playOrderChime() {
    try {
      if (audioCtx && chimeBuffer) {
        if (typeof audioCtx.setSinkId === 'function') {
          audioCtx.setSinkId('').catch(() => {});
        }
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const src = audioCtx.createBufferSource();
        src.buffer = chimeBuffer;
        src.connect(audioCtx.destination);
        src.start(0);
        return;
      }
    } catch { }
    try {
      const a = new Audio('/order_ching.wav');
      a.volume = 1.0;
      a.play().catch(() => {});
    } catch { }
  }

  initChimeAudio();
  document.addEventListener('pointerdown', () => {
    if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
    syncAtelierVideoPlayback();
  }, { once: true });

  // =========================================================================
  // 1b. SEAMLESS ATELIER BACKGROUND VIDEO ("MEIN SEELENFUNKE")
  // =========================================================================
  const atelierVideo = document.getElementById('atelier-bg-video');
  let currentWallpaperMode = 'AtelierVideo';
  let serverHasAtelierVideo = true;

  function syncAtelierVideoPlayback() {
    if (!atelierVideo) return;
    const isFocus = document.body.classList.contains('focus-mode');
    const isFunkiraOpen = !document.getElementById('funkira-stage').classList.contains('hidden');
    const useKosmos = currentWallpaperMode === 'Kosmos' || !serverHasAtelierVideo;

    document.body.classList.toggle('kosmos-wallpaper', useKosmos);

    if (useKosmos || isFocus || isFunkiraOpen) {
      if (!atelierVideo.paused) {
        try { atelierVideo.pause(); } catch { }
      }
    } else {
      if (atelierVideo.paused) {
        atelierVideo.play().catch(() => {});
      }
    }
  }

  async function setWallpaperMode(mode) {
    currentWallpaperMode = mode === 'Kosmos' ? 'Kosmos' : 'AtelierVideo';
    syncAtelierVideoPlayback();
    try {
      await fetch('/api/settings/wallpaper', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ wallpaperMode: currentWallpaperMode })
      });
    } catch { }
  }

  if (atelierVideo) {
    atelierVideo.addEventListener('canplay', () => syncAtelierVideoPlayback());
    atelierVideo.addEventListener('error', () => {
      serverHasAtelierVideo = false;
      syncAtelierVideoPlayback();
    });
  }

  // =========================================================================
  // 1c. WORKSHOP PRODUCTION / LASER COUNTDOWN TIMER
  // =========================================================================
  let workshopTimerRemain = 0;
  let workshopTimerLabel = 'Timer';
  let workshopTimerActive = false;

  function formatTimerMmSs(sec) {
    const s = Math.max(0, Math.floor(sec));
    const m = Math.floor(s / 60);
    const r = s % 60;
    return `${String(m).padStart(2, '0')}:${String(r).padStart(2, '0')}`;
  }

  function updateWorkshopTimerDom() {
    const pill1 = document.getElementById('workshop-timer-pill');
    const pill2 = document.getElementById('funkira-timer-mirror');
    if (!workshopTimerActive || workshopTimerRemain <= 0) {
      pill1?.classList.add('hidden');
      pill2?.classList.add('hidden');
      return;
    }
    const fmt = formatTimerMmSs(workshopTimerRemain);
    document.getElementById('workshop-timer-label').textContent = workshopTimerLabel;
    document.getElementById('workshop-timer-val').textContent = fmt;
    document.getElementById('funkira-timer-mirror-lbl').textContent = workshopTimerLabel;
    document.getElementById('funkira-timer-mirror-val').textContent = fmt;
    pill1?.classList.remove('hidden');
    pill2?.classList.remove('hidden');
  }

  setInterval(() => {
    if (!workshopTimerActive) return;
    workshopTimerRemain -= 1;
    if (workshopTimerRemain <= 0) {
      workshopTimerActive = false;
      workshopTimerRemain = 0;
      updateWorkshopTimerDom();
      triggerOrderCelebration();
    } else {
      updateWorkshopTimerDom();
    }
  }, 1000);

  // =========================================================================
  // 2. LOCKED 60-FPS GPU SPRITE PORTAL ENGINE (ZERO PER-FRAME GRADIENTS)
  // =========================================================================
  const portalCanvas = document.getElementById('portal-canvas');
  const pCtx = portalCanvas.getContext('2d', { alpha: true });

  let activeWorkspace = 'gewerbe'; // 'gewerbe' | 'desktop'
  let celebrationRemainSec = 0;
  let greenBlend = 0; // 0 = Gold, 1 = Emerald

  function bakeSprites(isEmerald) {
    const pri = isEmerald ? [16, 185, 129] : [197, 160, 89];
    const bri = isEmerald ? [167, 243, 208] : [253, 230, 138];
    const dep = isEmerald ? [5, 110, 75] : [120, 82, 24];

    const aura = document.createElement('canvas');
    aura.width = aura.height = 512;
    const ac = aura.getContext('2d');
    const g1 = ac.createRadialGradient(256, 256, 0, 256, 256, 248);
    g1.addColorStop(0, `rgba(${pri[0]},${pri[1]},${pri[2]},${isEmerald ? 0.52 : 0.40})`);
    g1.addColorStop(0.45, `rgba(${dep[0]},${dep[1]},${dep[2]},0.20)`);
    g1.addColorStop(1, `rgba(${pri[0]},${pri[1]},${pri[2]},0)`);
    ac.fillStyle = g1;
    ac.fillRect(0, 0, 512, 512);

    const ring = document.createElement('canvas');
    ring.width = ring.height = 600;
    const rc = ring.getContext('2d');
    rc.translate(300, 300);
    rc.strokeStyle = `rgba(${bri[0]},${bri[1]},${bri[2]},0.62)`;
    rc.lineWidth = 1.6;
    rc.setLineDash([6, 12]);
    rc.beginPath();
    rc.arc(0, 0, 252, 0, Math.PI * 2);
    rc.stroke();
    rc.setLineDash([2, 8]);
    rc.strokeStyle = `rgba(${pri[0]},${pri[1]},${pri[2]},0.45)`;
    rc.beginPath();
    rc.arc(0, 0, 234, 0, Math.PI * 2);
    rc.stroke();

    const ribbon = document.createElement('canvas');
    ribbon.width = ribbon.height = 560;
    const rbc = ribbon.getContext('2d');
    rbc.translate(280, 280);
    for (let i = 0; i < 3; i++) {
      rbc.rotate((Math.PI * 2) / 3);
      const rg = rbc.createLinearGradient(-210, 0, 210, 0);
      rg.addColorStop(0, `rgba(${pri[0]},${pri[1]},${pri[2]},0)`);
      rg.addColorStop(0.5, `rgba(${bri[0]},${bri[1]},${bri[2]},0.42)`);
      rg.addColorStop(1, `rgba(${pri[0]},${pri[1]},${pri[2]},0)`);
      rbc.strokeStyle = rg;
      rbc.lineWidth = 2.4;
      rbc.beginPath();
      rbc.ellipse(0, 0, 206, 152, 0.35, 0, Math.PI * 1.35);
      rbc.stroke();
    }

    const sphere = document.createElement('canvas');
    sphere.width = sphere.height = 440;
    const sc = sphere.getContext('2d');
    const r = 195;
    const sg = sc.createRadialGradient(185, 165, 12, 220, 220, r);
    sg.addColorStop(0, `rgba(${bri[0]},${bri[1]},${bri[2]},0.20)`);
    sg.addColorStop(0.58, `rgba(12,12,16,0.72)`);
    sg.addColorStop(0.88, `rgba(${pri[0]},${pri[1]},${pri[2]},0.36)`);
    sg.addColorStop(1, `rgba(${bri[0]},${bri[1]},${bri[2]},0.78)`);
    sc.fillStyle = sg;
    sc.beginPath();
    sc.arc(220, 220, r, 0, Math.PI * 2);
    sc.fill();
    sc.lineWidth = 2.2;
    sc.strokeStyle = `rgba(${bri[0]},${bri[1]},${bri[2]},0.72)`;
    sc.stroke();

    const rim = sc.createLinearGradient(220, 28, 220, 140);
    rim.addColorStop(0, 'rgba(255,255,255,0.42)');
    rim.addColorStop(1, 'rgba(255,255,255,0)');
    sc.fillStyle = rim;
    sc.beginPath();
    sc.ellipse(220, 78, 122, 42, 0, 0, Math.PI * 2);
    sc.fill();

    const spark = document.createElement('canvas');
    spark.width = spark.height = 32;
    const spc = spark.getContext('2d');
    const spg = spc.createRadialGradient(16, 16, 0, 16, 16, 15);
    spg.addColorStop(0, '#FFFFFF');
    spg.addColorStop(0.32, `rgba(${bri[0]},${bri[1]},${bri[2]},0.9)`);
    spg.addColorStop(1, `rgba(${pri[0]},${pri[1]},${pri[2]},0)`);
    spc.fillStyle = spg;
    spc.fillRect(0, 0, 32, 32);

    return { aura, ring, ribbon, sphere, spark };
  }

  const spritesGold = bakeSprites(false);
  const spritesEmerald = bakeSprites(true);

  const sparks = Array.from({ length: 88 }, (_, i) => ({
    angle: (i / 88) * Math.PI * 2 + Math.random() * 0.4,
    baseR: 0.48 + Math.random() * 0.56,
    speed: (0.14 + Math.random() * 0.28) * (i % 2 === 0 ? 1 : -1),
    phase: Math.random() * Math.PI * 2,
    size: 7 + Math.random() * 15
  }));

  const shockwaves = [];

  function triggerOrderCelebration() {
    celebrationRemainSec = 5.0;
    document.body.classList.add('order-celebration');
    shockwaves.push({ r: 0.36, alpha: 0.95 });
    playOrderChime();
  }

  const fkCanvas = document.getElementById('funkira-avatar-canvas');
  const fkCtx = fkCanvas.getContext('2d');
  const fkMagicCanvas = document.getElementById('funkira-magic-canvas');
  const fkMagicCtx = fkMagicCanvas ? fkMagicCanvas.getContext('2d') : null;
  let fkMagicDissolveActive = false;

  function resizeCanvases() {
    const dpr = window.innerWidth >= 2000 ? 1 : Math.min(window.devicePixelRatio || 1, 1.5);
    portalCanvas.width = Math.floor(window.innerWidth * dpr);
    portalCanvas.height = Math.floor(window.innerHeight * dpr);
    pCtx.setTransform(dpr, 0, 0, dpr, 0, 0);

    const dc = document.getElementById('desktop-sparks-canvas');
    dc.width = Math.floor(window.innerWidth * dpr);
    dc.height = Math.floor(window.innerHeight * dpr);
    dc.getContext('2d').setTransform(dpr, 0, 0, dpr, 0, 0);

    if (!window.Funkira3D) {
      fkCanvas.width = Math.floor(1240 * dpr);
      fkCanvas.height = Math.floor(1040 * dpr);
      fkCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
    } else {
      fkCanvas.width = 2;
      fkCanvas.height = 2;
    }

    if (fkMagicCanvas && fkMagicCtx) {
      fkMagicCanvas.width = Math.floor(window.innerWidth * dpr);
      fkMagicCanvas.height = Math.floor(window.innerHeight * dpr);
      fkMagicCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
  }
  window.addEventListener('resize', resizeCanvases);
  resizeCanvases();

  let lastTs = performance.now();
  let rotA = 0;
  let rotB = 0;
  let sheenCycle = 0;
  let fpsFrames = 0;
  let fpsTimer = 0;
  const fpsEl = document.getElementById('gewerbe-fps');

  const deskCanvas = document.getElementById('desktop-sparks-canvas');
  const dCtx = deskCanvas.getContext('2d');
  const deskParticles = Array.from({ length: 42 }, () => ({
    x: Math.random() * window.innerWidth,
    y: Math.random() * window.innerHeight,
    vy: -10 - Math.random() * 18,
    vx: (Math.random() - 0.5) * 8,
    sz: 4 + Math.random() * 7,
    ph: Math.random() * Math.PI * 2
  }));
  const ripples = [];
  window.addEventListener('pointermove', (e) => {
    resetMouseIdle();
    if (activeWorkspace === 'desktop' && ripples.length < 18 && Math.random() < 0.28) {
      ripples.push({ x: e.clientX, y: e.clientY, r: 4, a: 0.28 });
    }
  });

  function renderLoop(now) {
    requestAnimationFrame(renderLoop);
    const dt = Math.min(0.05, Math.max(0.001, (now - lastTs) / 1000));
    lastTs = now;

    fpsFrames++;
    fpsTimer += dt;
    if (fpsTimer >= 1.0) {
      const fps = Math.round(fpsFrames / fpsTimer);
      fpsEl.textContent = `${fps} FPS`;
      fpsFrames = 0;
      fpsTimer = 0;
    }

    if (funkiraStageOpen) {
      sampleFunkiraVoiceFormants(dt);
      if (!fkIdleAtelierMode && !fkTikTokLiveMode) {
        updateFunkiraPersonTracker(dt);
      }
      if (window.Funkira3D && window.Funkira3D.running) {
        window.Funkira3D.setVoiceState(fkMouthOpenSmooth, fkIsSpeaking || fkActiveSources.length > 0);
        window.Funkira3D.setLookTarget(fkLookX, fkLookY, fkPersonDetected);
        window.Funkira3D.renderFrame(now);
      } else {
        renderFunkiraAvatar(dt, now / 1000);
      }
      if (fkMagicDissolveActive) {
        renderFunkiraMagicSparks(dt, now / 1000);
      }
      return;
    }

    if (activeWorkspace === 'gewerbe') {
      renderGewerbeStage(dt, now / 1000);
    } else {
      renderDesktopAmbient(dt, now / 1000);
    }

    if (fkMagicDissolveActive) {
      renderFunkiraMagicSparks(dt, now / 1000);
    }
  }

  function renderGewerbeStage(dt, tSec) {
    if (celebrationRemainSec > 0) {
      celebrationRemainSec -= dt;
      greenBlend = Math.min(1, greenBlend + dt * 3.2);
      if (celebrationRemainSec <= 0) {
        document.body.classList.remove('order-celebration');
      }
    } else if (greenBlend > 0) {
      greenBlend = Math.max(0, greenBlend - dt * 1.8);
    }

    const w = window.innerWidth;
    const h = window.innerHeight;
    pCtx.clearRect(0, 0, w, h);

    const isFocus = document.body.classList.contains('focus-mode');
    const cx = w * 0.5;
    const cy = h * (isFocus ? 0.505 : 0.475);
    const baseSize = Math.min(w, h) * (isFocus ? 0.60 : 0.56);

    const sp = greenBlend > 0.5 ? spritesEmerald : spritesGold;

    rotA += dt * 0.14 * (greenBlend > 0.5 ? 2.1 : 1.0);
    rotB -= dt * 0.22 * (greenBlend > 0.5 ? 2.1 : 1.0);
    sheenCycle = (sheenCycle + dt) % 10.0;

    const auraSz = baseSize * 1.38;
    pCtx.drawImage(sp.aura, cx - auraSz * 0.5, cy - auraSz * 0.5, auraSz, auraSz);

    pCtx.save();
    pCtx.translate(cx, cy);
    pCtx.rotate(rotA);
    pCtx.drawImage(sp.ring, -baseSize * 0.54, -baseSize * 0.54, baseSize * 1.08, baseSize * 1.08);
    pCtx.restore();

    pCtx.save();
    pCtx.translate(cx, cy);
    pCtx.rotate(rotB);
    pCtx.drawImage(sp.ribbon, -baseSize * 0.5, -baseSize * 0.5, baseSize, baseSize);
    pCtx.restore();

    const maxR = baseSize * 0.52;
    for (let i = 0; i < sparks.length; i++) {
      const s = sparks[i];
      s.angle += s.speed * dt * (greenBlend > 0.5 ? 1.8 : 1.0);
      const rad = (s.baseR + Math.sin(tSec * 1.4 + s.phase) * 0.04) * maxR;
      const sx = cx + Math.cos(s.angle) * rad;
      const sy = cy + Math.sin(s.angle) * rad;
      pCtx.globalAlpha = 0.35 + 0.55 * (0.5 + 0.5 * Math.sin(tSec * 2.6 + s.phase));
      pCtx.drawImage(sp.spark, sx - s.size * 0.5, sy - s.size * 0.5, s.size, s.size);
    }
    pCtx.globalAlpha = 1;

    for (let i = shockwaves.length - 1; i >= 0; i--) {
      const sw = shockwaves[i];
      sw.r += dt * 0.55;
      sw.alpha -= dt * 0.52;
      if (sw.alpha <= 0) {
        shockwaves.splice(i, 1);
        continue;
      }
      pCtx.strokeStyle = `rgba(167,243,208,${sw.alpha.toFixed(2)})`;
      pCtx.lineWidth = 2.5;
      pCtx.beginPath();
      pCtx.arc(cx, cy, sw.r * baseSize, 0, Math.PI * 2);
      pCtx.stroke();
    }

    const sphSz = baseSize * 0.72;
    pCtx.drawImage(sp.sphere, cx - sphSz * 0.5, cy - sphSz * 0.5, sphSz, sphSz);

    if (sheenCycle < 1.45) {
      const t = sheenCycle / 1.45;
      const ease = 0.5 - 0.5 * Math.cos(t * Math.PI);
      const offset = (ease - 0.5) * sphSz * 1.15;
      const alpha = Math.sin(t * Math.PI) * 0.36;
      const orbR = sphSz * 0.43;

      pCtx.save();
      pCtx.beginPath();
      pCtx.arc(cx, cy, orbR, 0, Math.PI * 2);
      pCtx.clip();
      pCtx.translate(cx + offset, cy + offset * 0.45);
      pCtx.rotate(-0.55);
      pCtx.fillStyle = `rgba(255,250,235,${alpha.toFixed(3)})`;
      pCtx.fillRect(-18, -orbR * 1.4, 24, orbR * 2.8);
      pCtx.fillStyle = `rgba(255,255,255,${(alpha * 0.55).toFixed(3)})`;
      pCtx.fillRect(16, -orbR * 1.4, 8, orbR * 2.8);
      pCtx.restore();
    }
  }

  function renderDesktopAmbient(dt, tSec) {
    const w = window.innerWidth;
    const h = window.innerHeight;
    dCtx.clearRect(0, 0, w, h);

    for (const p of deskParticles) {
      p.y += p.vy * dt;
      p.x += p.vx * dt;
      if (p.y < -10) { p.y = h + 10; p.x = Math.random() * w; }
      dCtx.globalAlpha = 0.22 + 0.35 * (0.5 + 0.5 * Math.sin(tSec * 2 + p.ph));
      dCtx.drawImage(spritesGold.spark, p.x, p.y, p.sz * 2, p.sz * 2);
    }
    dCtx.globalAlpha = 1;

    for (let i = ripples.length - 1; i >= 0; i--) {
      const rp = ripples[i];
      rp.r += dt * 48;
      rp.a -= dt * 0.38;
      if (rp.a <= 0) { ripples.splice(i, 1); continue; }
      dCtx.strokeStyle = `rgba(197,160,89,${rp.a.toFixed(2)})`;
      dCtx.lineWidth = 1.2;
      dCtx.beginPath();
      dCtx.arc(rp.x, rp.y, rp.r, 0, Math.PI * 2);
      dCtx.stroke();
    }
  }

  // =========================================================================
  // 2b. FUNKIRA 60-FPS ARTICULATED RIG, EMOTIONS, CAMERA TRACKER & MAGIC EXIT
  // =========================================================================
  const fkSprites = {
    head: new Image(),
    torso: new Image(),
    shell: new Image(),
    clean: new Image(),
    ready: false
  };
  let fkRigLoaded = 0;
  for (const [key, file] of [
    ['head', 'funkira_head.png'],
    ['torso', 'funkira_torso.png'],
    ['shell', 'funkira_shell.png'],
    ['clean', 'funkira_clean.png']
  ]) {
    fkSprites[key].onload = () => {
      if (key === 'head' || key === 'torso') {
        fkRigLoaded++;
        if (fkRigLoaded >= 2) fkSprites.ready = true;
      }
    };
    fkSprites[key].src = `/funkira/${file}?v=20261112v5`;
  }

  let funkiraStageOpen = false;
  let pendingOpenFunkiraAfterLogin = false;
  let fkWs = null;
  let fkSetupComplete = false;
  let fkSetupCompleteAtMs = 0;
  let fkStageOpenedAtMs = 0;
  let fkIsSpeaking = false;
  let fkIsAiTurn = false;
  let fkGenComplete = false;
  let fkToolRunning = false;
  let fkMicMuted = false;
  let fkCamEnabled = true;
  let fkUserRms = 0;
  let fkLastUserVoiceMs = 0;
  let fkHasSpokenInTurn = false;
  let fkLastSpeechEndMs = 0;
  let fkLastAiPacketMs = 0;
  let fkPendingUiAfterSpeech = null;
  let fkPendingUiSafetyTimer = null;

  // Unified WebAudio Engine (Single AudioContext for 24 kHz Voice + 60-FPS Lip-Sync + 16 kHz Mic)
  let fkPlayCtx = null;
  let fkPlayGain = null;
  let fkPlayAnalyser = null;
  let fkFreqBins = null;
  let fkTimeBins = null;
  let fkNextPlayTime = 0;
  let fkActiveSources = [];
  let fkTurnDrainTimeout = null;

  let fkMicStream = null;
  let fkMediaStream = null;
  let fkMicSource = null;
  let fkMicProcessor = null;
  let fkCamInterval = null;
  let fkPingInterval = null;
  let fkFaceCheckInterval = null;
  let fkIdleMonitorInterval = null;
  let fkWakePollInterval = null;

  // Barge-In, 0-Token Atelier Idle, Face Guard & TikTok Sandbox State
  let fkCurrentOutputRms = 0;
  let fkBargeConsecHits = 0;
  let fkPreBargePcmQueue = [];
  let fkLastInteractionMs = 0;
  let fkIdlePromptAsked = false;
  let fkIdlePromptSentAtMs = 0;
  let fkIdlePromptFinishMs = 0;
  let fkIdleAtelierMode = false;
  let fkWakeTransitionBusy = false;
  let fkTikTokLiveMode = false;
  let fkTikTokHandle = '';
  let fkTikTokRosesCount = 0;
  let fkOwnerFaceEnrolled = false;
  let fkFaceIdentity = 'unenrolled';
  let fkLatestShopLevel = 2;
  const fkSnapCanvas = document.createElement('canvas');
  const fkSnapCtx = fkSnapCanvas.getContext('2d');

  // Smooth 60-FPS Facial, Gaze, Emotion & Articulated Arm IK State
  let fkMouthOpenSmooth = 0;
  let fkMouthWideSmooth = 0.5;
  let fkHeadTiltSmooth = 0;
  let fkBlinkCountdown = 2.4;
  let fkBlinkProgress = -1; // -1 = open, 0..0.165 = blinking
  let fkQueuedDoubleBlink = false;

  // Emotion & Gesture State Machine
  let fkEmotion = 'happy'; // neutral | happy | laugh | sweet | angry | sad | cry | curious | proud | sleepy
  let fkGesture = 'wave';  // idle | wave | point_left | point_right | point_up | point_down | point_camera | point_orders | point_timer | point_user | hands_on_hips | hands_on_cheeks | cheer | wipe_tear
  let fkExpressUntilMs = 0;
  let fkExpressLockedByTool = false;
  let fkTearDrops = [];

  // Smooth Articulated Wrist Targets & Hand Aim Angles (in 1024x1024 rig space)
  let fkLeftWrist = { x: 224, y: 924, aim: 1.35 };
  let fkRightWrist = { x: 882, y: 525, aim: -1.45 };

  // Real-Time Logitech C920 Person Tracker State (64x36 @ 4.5 Hz = ultra-low GPU readback)
  const fkTrackCanvas = document.createElement('canvas');
  fkTrackCanvas.width = 64;
  fkTrackCanvas.height = 36;
  const fkTrackCtx = fkTrackCanvas.getContext('2d', { willReadFrequently: true });
  let fkPrevLuma = null;
  let fkLastTrackSampleMs = 0;
  let fkPersonDetected = false;
  let fkPersonConfidence = 0;
  let fkPersonRawX = 0.5; // mirrored 0..1 (0 = screen left, 1 = screen right)
  let fkPersonRawY = 0.45;
  let fkLookTargetX = 0;  // -1..+1
  let fkLookTargetY = 0;  // -1..+1
  let fkLookX = 0;
  let fkLookY = 0;
  let fkBodyFollowX = 0;
  let fkPersonConsecHits = 0;
  let fkPersonLastSeenMs = 0;
  let fkPersonWasAway = false;
  let fkProactiveSessionSent = false;
  let fkNativeFaceDetector = null;
  let fkFaceDetectBusy = false;
  try {
    if (typeof window.FaceDetector === 'function') {
      fkNativeFaceDetector = new window.FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
    }
  } catch { fkNativeFaceDetector = null; }

  // Magic Dissolve Particle State ("Magie aktivieren" Light Exit)
  let fkMagicParticles = [];
  let fkMagicElapsed = 0;
  let fkMagicDuration = 1.15;
  let fkMagicSrc = { x: 640, y: 460 };
  let fkMagicDst = { x: 980, y: 36 };
  let fkMagicAbsorbTriggered = false;
  let fkMagicDoneCallback = null;

  function setFunkiraExpress(emotion, gesture, durationSec = 5.5, fromTool = false) {
    const now = Date.now();
    if (!fromTool && fkExpressLockedByTool && now < fkExpressUntilMs) {
      // Only upgrade gesture if tool didn't lock a specific pointing gesture
      if (gesture && gesture !== 'idle' && fkGesture === 'idle') {
        fkGesture = gesture;
      }
      return;
    }
    if (emotion) fkEmotion = emotion;
    if (gesture) {
      fkGesture = gesture;
    } else if (emotion === 'sweet') {
      fkGesture = 'hands_on_cheeks';
    } else if (emotion === 'angry') {
      fkGesture = 'hands_on_hips';
    } else if (emotion === 'cry') {
      fkGesture = 'wipe_tear';
    } else if (emotion === 'proud') {
      fkGesture = 'cheer';
    }
    fkExpressUntilMs = now + Math.max(1.5, Math.min(20, durationSec)) * 1000;
    fkExpressLockedByTool = !!fromTool;

    if (window.Funkira3D) {
      const emo3d = fkEmotion === 'laugh' ? 'laughing' : (fkEmotion === 'cry' ? 'sad' : fkEmotion);
      window.Funkira3D.setExpression(emo3d, fkGesture, Math.max(1500, durationSec * 1000));
    }
  }

  function inferFunkiraExpressFromText(text, isUser = false) {
    if (!text) return;
    const t = text.toLowerCase();

    if (/(schalt dich ab|abschalten|funkira aus|funkira beenden|tschüss|tschau|auf wiedersehen|gute nacht|bis morgen|schlaf gut)/.test(t)) {
      setFunkiraExpress('happy', 'wave', 10, false);
      return;
    }
    if (isUser) {
      if (/(wandbild|bild an der wand|gruppenfoto|alte kollegen|frühere kollegen|alten kollegen|gründer-team|wer ist auf dem bild|wer sind die auf dem foto|wer hängt da)/.test(t)) {
        setFunkiraExpress('proud', 'point_photo', 8.5, false);
      } else if (/(haha|hihi|witzig|lustig)/.test(t)) setFunkiraExpress('laugh', 'idle', 4.5, false);
      else if (/(danke|lieb|süß|toll gemacht|super)/.test(t)) setFunkiraExpress('sweet', 'hands_on_cheeks', 5.0, false);
      return;
    }

    // Real-time AI speech sentiment & pointing reflexes
    if (/(gruppenfoto|wandbild|bild an der wand|gründer-team|alte kollegen|frühere kollegen|alten kollegen|buchi|bestelli|produkti|marketi|lasi|dr\.?\s*funki|einkaufi|systemi|agenti|globi)/.test(t)) {
      setFunkiraExpress('proud', 'point_photo', 8.5, false);
    } else if (/(haha|hihi|herrlich|witzig|lachen|kicher)/.test(t)) {
      setFunkiraExpress('laugh', 'cheer', 5.0, false);
    } else if (/(ab ins bett|sofort feierabend|viel zu spät|nachtruhe|streng sein|sauer|schluss für heute|husch ins bett)/.test(t)) {
      setFunkiraExpress('angry', 'hands_on_hips', 6.5, false);
    } else if (/(weinen|tränen|schnief|buhu)/.test(t)) {
      setFunkiraExpress('cry', 'wipe_tear', 6.0, false);
    } else if (/(traurig|schade|tut mir leid|oh nein|leider)/.test(t)) {
      setFunkiraExpress('sad', 'idle', 5.0, false);
    } else if (/(süß|knuffig|hab dich lieb|herzensprojekt|wie lieb|freut mich riesig|kuschel)/.test(t)) {
      setFunkiraExpress('sweet', 'hands_on_cheeks', 5.5, false);
    } else if (/(stolz|boss|stark gemacht|perfekt|geschafft|spitze|läuft bei uns)/.test(t)) {
      setFunkiraExpress('proud', 'cheer', 5.5, false);
    } else if (/(müde|gähn|schlafen gehen|bettfertig|regeneration)/.test(t)) {
      setFunkiraExpress('sleepy', 'idle', 5.5, false);
    } else if (/(bestellung|aufträge|express|bestellansicht)/.test(t)) {
      setFunkiraExpress('happy', 'point_orders', 4.5, false);
    } else if (/(timer|stoppuhr|countdown)/.test(t)) {
      setFunkiraExpress('curious', 'point_timer', 4.5, false);
    } else if (/(sehe dich|vor der kamera|schau dich an|dein pullover|dein gesicht)/.test(t)) {
      setFunkiraExpress('happy', 'point_user', 4.8, false);
    }
  }

  function updateFunkiraPersonTracker(dt) {
    const now = Date.now();
    const vidEl = document.getElementById('funkira-cam-video');
    const targetDot = document.getElementById('funkira-eye-target');

    if (fkExpressUntilMs > 0 && now > fkExpressUntilMs) {
      fkExpressUntilMs = 0;
      fkExpressLockedByTool = false;
      const hr = new Date().getHours();
      fkEmotion = (hr >= 22 || hr < 6) ? 'sleepy' : 'neutral';
      fkGesture = 'idle';
    }

    const canTrack = funkiraStageOpen && fkCamEnabled && !fkIdleAtelierMode && !fkTikTokLiveMode && vidEl && vidEl.readyState >= 2 && vidEl.videoWidth > 0;
    if (canTrack && (now - fkLastTrackSampleMs) >= 220) {
      fkLastTrackSampleMs = now;
      const tw = 64;
      const th = 36;
      fkTrackCtx.drawImage(vidEl, 0, 0, tw, th);
      const img = fkTrackCtx.getImageData(0, 0, tw, th).data;
      if (!fkPrevLuma || fkPrevLuma.length !== tw * th) {
        fkPrevLuma = new Float32Array(tw * th);
      }

      let skinWeightSum = 0, skinXSum = 0, skinYSum = 0;
      let motWeightSum = 0, motXSum = 0, motYSum = 0;

      for (let y = 2; y < th - 2; y++) {
        const ny = y / th;
        const headZoneBoost = (ny >= 0.08 && ny <= 0.74) ? 1.45 : 0.65;
        for (let x = 2; x < tw - 2; x++) {
          const idx = y * tw + x;
          const p = idx * 4;
          const r = img[p];
          const g = img[p + 1];
          const b = img[p + 2];
          const luma = 0.299 * r + 0.587 * g + 0.114 * b;

          const diff = Math.abs(luma - fkPrevLuma[idx]);
          fkPrevLuma[idx] = fkPrevLuma[idx] * 0.45 + luma * 0.55;
          if (diff > 14) {
            const mw = (diff - 14) * headZoneBoost;
            motWeightSum += mw;
            motXSum += x * mw;
            motYSum += y * mw;
          }

          if (r > 58 && g > 34 && b > 20 && r > g * 1.05 && r > b * 1.15 && (r - b) > 15) {
            const cb = 128 - 0.168736 * r - 0.331264 * g + 0.5 * b;
            const cr = 128 + 0.5 * r - 0.418688 * g - 0.081312 * b;
            if (cb >= 76 && cb <= 133 && cr >= 131 && cr <= 179) {
              const sw = headZoneBoost * (1.0 + Math.min(1.0, (cr - 131) / 28));
              skinWeightSum += sw;
              skinXSum += x * sw;
              skinYSum += y * sw;
            }
          }
        }
      }

      const hasMotion = motWeightSum > 135;
      const hasSkin = skinWeightSum > 16;

      if (hasMotion || hasSkin) {
        let rawUnmirroredX = 0.5;
        let rawY = 0.44;
        if (hasMotion && hasSkin) {
          const mx = (motXSum / motWeightSum) / tw;
          const my = (motYSum / motWeightSum) / th;
          const sx = (skinXSum / skinWeightSum) / tw;
          const sy = (skinYSum / skinWeightSum) / th;
          rawUnmirroredX = mx * 0.46 + sx * 0.54;
          rawY = my * 0.42 + sy * 0.58;
        } else if (hasMotion) {
          rawUnmirroredX = (motXSum / motWeightSum) / tw;
          rawY = (motYSum / motWeightSum) / th;
        } else {
          rawUnmirroredX = (skinXSum / skinWeightSum) / tw;
          rawY = (skinYSum / skinWeightSum) / th;
        }

        // Mirror X so screen-left corresponds to Alina's physical left in front of the monitor!
        const mirX = 1.0 - Math.max(0.06, Math.min(0.94, rawUnmirroredX));
        const clampedY = Math.max(0.10, Math.min(0.88, rawY));
        fkPersonRawX += (mirX - fkPersonRawX) * 0.42;
        fkPersonRawY += (clampedY - fkPersonRawY) * 0.38;
        fkPersonConfidence = Math.min(1, fkPersonConfidence + 0.28);
        fkPersonConsecHits++;
        if (fkPersonConsecHits >= 2) {
          if (fkPersonWasAway && fkSetupComplete && !fkIsSpeaking && !fkIsAiTurn && !fkHasSpokenInTurn && fkWs && fkWs.readyState === WebSocket.OPEN) {
            fkPersonWasAway = false;
            const posDesc = fkPersonRawX < 0.36 ? 'links vor dem Monitor' : (fkPersonRawX > 0.64 ? 'rechts vor dem Monitor' : 'direkt vor dem Monitor');
            fkWs.send(JSON.stringify({ type: 'personPresenceEvent', reason: 'person_returned', position: posDesc }));
          }
          fkPersonDetected = true;
          fkPersonLastSeenMs = now;
        }
      } else {
        fkPersonConfidence = Math.max(0, fkPersonConfidence - 0.08);
        fkPersonConsecHits = 0;
        if (fkPersonDetected && (now - fkPersonLastSeenMs) > 4500) {
          fkPersonDetected = false;
        }
        if (!fkPersonDetected && fkPersonLastSeenMs > 0 && (now - fkPersonLastSeenMs) > 45000) {
          fkPersonWasAway = true;
        }
      }

      if (targetDot) {
        if (fkPersonDetected && fkPersonConfidence > 0.25) {
          targetDot.classList.remove('hidden');
          targetDot.style.left = `${(fkPersonRawX * 100).toFixed(1)}%`;
          targetDot.style.top = `${(fkPersonRawY * 100).toFixed(1)}%`;
        } else {
          targetDot.classList.add('hidden');
        }
      }

      // Proactive Routine & Late-Night Greeting when Funkira sees Alina on Session Start
      if (fkSetupComplete && !fkProactiveSessionSent && fkWs && fkWs.readyState === WebSocket.OPEN) {
        const sinceReady = now - fkSetupCompleteAtMs;
        if (!fkHasSpokenInTurn && !fkIsSpeaking && !fkIsAiTurn) {
          if ((fkPersonDetected && sinceReady >= 1250) || (fkCamEnabled && sinceReady >= 2500)) {
            fkProactiveSessionSent = true;
            const posDesc = fkPersonDetected
              ? (fkPersonRawX < 0.36 ? 'links vor dem Monitor' : (fkPersonRawX > 0.64 ? 'rechts vor dem Monitor' : 'direkt vor dem Monitor'))
              : 'vor dem Werkstatt-Monitor';
            fkWs.send(JSON.stringify({ type: 'personPresenceEvent', reason: 'session_start', position: posDesc }));
          }
        } else if (fkHasSpokenInTurn || fkIsSpeaking) {
          fkProactiveSessionSent = true;
        }
      }
    }

    // Compute smooth Look-At vector (-1..+1)
    const tSec = now / 1000;
    if (fkToolRunning) {
      fkLookTargetX = 0.28;
      fkLookTargetY = -0.32;
    } else if (fkPersonDetected) {
      fkLookTargetX = Math.max(-1, Math.min(1, (fkPersonRawX - 0.5) * 2.35));
      fkLookTargetY = Math.max(-1, Math.min(1, (fkPersonRawY - 0.45) * 2.15));
    } else {
      fkLookTargetX = Math.sin(tSec * 0.72) * 0.22;
      fkLookTargetY = Math.cos(tSec * 0.58) * 0.14;
    }

    fkLookX += (fkLookTargetX - fkLookX) * Math.min(1, dt * 6.5);
    fkLookY += (fkLookTargetY - fkLookY) * Math.min(1, dt * 6.5);
    fkBodyFollowX += (fkLookX * 26 - fkBodyFollowX) * Math.min(1, dt * 3.8);
  }

  function sampleFunkiraVoiceFormants(dt) {
    let targetOpen = 0;
    let targetWide = 0.45;

    const hasPlayingAudio = fkActiveSources.length > 0 && fkPlayAnalyser && fkFreqBins && fkTimeBins;
    if (hasPlayingAudio) {
      fkPlayAnalyser.getByteTimeDomainData(fkTimeBins);
      let sumSq = 0;
      for (let i = 0; i < fkTimeBins.length; i++) {
        const norm = (fkTimeBins[i] - 128) / 128;
        sumSq += norm * norm;
      }
      const rms = Math.sqrt(sumSq / fkTimeBins.length);
      fkCurrentOutputRms = rms;

      fkPlayAnalyser.getByteFrequencyData(fkFreqBins);
      let lowBand = 0;
      let highBand = 0;
      for (let i = 2; i <= 9 && i < fkFreqBins.length; i++) lowBand += fkFreqBins[i];
      for (let i = 10; i <= 32 && i < fkFreqBins.length; i++) highBand += fkFreqBins[i];
      lowBand /= 8;
      highBand /= 23;

      targetOpen = Math.min(1.0, Math.pow(Math.max(0, rms - 0.008) * 4.6, 0.78));
      const ratio = highBand / Math.max(12, lowBand + highBand);
      targetWide = Math.max(0.18, Math.min(0.95, 0.25 + ratio * 1.35));
    } else if (fkIsSpeaking) {
      fkCurrentOutputRms = 0.06;
      const t = performance.now() / 1000;
      targetOpen = 0.25 + 0.35 * Math.abs(Math.sin(t * 11.5)) * (0.5 + 0.5 * Math.cos(t * 4.3));
      targetWide = 0.45 + 0.25 * Math.sin(t * 6.0);
    } else {
      fkCurrentOutputRms = 0;
    }

    const attackRate = targetOpen > fkMouthOpenSmooth ? 28.0 : 15.5;
    fkMouthOpenSmooth += (targetOpen - fkMouthOpenSmooth) * Math.min(1, dt * attackRate);
    fkMouthWideSmooth += (targetWide - fkMouthWideSmooth) * Math.min(1, dt * 12.0);
  }

  function traceRoundedRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + r);
    ctx.lineTo(x + w, y + h - r);
    ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    ctx.lineTo(x + r, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - r);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
  }

  function drawFourPointStar(ctx, cx, cy, rOuter, rInner, rot = 0) {
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(rot);
    ctx.beginPath();
    for (let i = 0; i < 8; i++) {
      const r = (i % 2 === 0) ? rOuter : rInner;
      const a = (i * Math.PI) / 4;
      const px = Math.cos(a) * r;
      const py = Math.sin(a) * r;
      if (i === 0) ctx.moveTo(px, py);
      else ctx.lineTo(px, py);
    }
    ctx.closePath();
    ctx.fill();
    ctx.restore();
  }

  function drawFunkiraEye(ctx, side, cx, cy, gazePxX, gazePxY, blinkAmt, emotion, tSec) {
    // side: -1 = viewer-left eye, +1 = viewer-right eye
    ctx.save();
    ctx.translate(cx + gazePxX * 0.28, cy + gazePxY * 0.24);

    // Joyful laughing ^ ^ arches when laughing or full blink during happy/sweet
    if (emotion === 'laugh') {
      const bounce = Math.sin(tSec * 14) * 3.5;
      ctx.translate(0, bounce);
      ctx.strokeStyle = '#0B090E';
      ctx.lineWidth = 13;
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.moveTo(-72, 18);
      ctx.quadraticCurveTo(0, -56, 72, 18);
      ctx.stroke();

      // Warm rose-gold shimmer accent along top of laughing lid
      ctx.strokeStyle = 'rgba(236, 148, 168, 0.72)';
      ctx.lineWidth = 5.5;
      ctx.beginPath();
      ctx.moveTo(-58, 8);
      ctx.quadraticCurveTo(0, -44, 58, 8);
      ctx.stroke();

      // Outer lush eyelashes
      ctx.strokeStyle = '#0B090E';
      ctx.lineWidth = 9;
      ctx.beginPath();
      ctx.moveTo(side * 62, 8);
      ctx.lineTo(side * 94, -14);
      ctx.moveTo(side * 68, 18);
      ctx.lineTo(side * 96, 8);
      ctx.stroke();
      ctx.restore();
      return;
    }

    const scale = emotion === 'sweet' ? 1.07 : (emotion === 'angry' ? 0.96 : 1.0);
    const rx = 86 * scale;
    const sleepyClose = emotion === 'sleepy' ? 0.54 : 0;
    const openFactor = Math.max(0.05, (1.0 - blinkAmt) * (1.0 - sleepyClose));
    const ry = 92 * scale * openFactor;

    if (openFactor <= 0.14) {
      // Closed blink line with curved outer lashes
      ctx.strokeStyle = '#0B090E';
      ctx.lineWidth = 11.5;
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.moveTo(-rx, 6);
      ctx.quadraticCurveTo(0, 26, rx, 6);
      ctx.stroke();

      ctx.lineWidth = 8.5;
      ctx.beginPath();
      ctx.moveTo(side * (rx - 8), 8);
      ctx.lineTo(side * (rx + 22), -6);
      ctx.stroke();
      ctx.restore();
      return;
    }

    // 1. Eye socket outer ink rim & subtle warm cream sclera rim (matches reference art!)
    ctx.beginPath();
    ctx.ellipse(0, 0, rx + 7, ry + 7, 0, 0, Math.PI * 2);
    ctx.fillStyle = '#09080C';
    ctx.fill();

    ctx.beginPath();
    ctx.ellipse(0, 0, rx, ry, 0, 0, Math.PI * 2);
    ctx.fillStyle = '#FFF5F6';
    ctx.fill();

    // 2. Clip inside open eye ellipse for gliding 3D iris, pupil & tear effects
    ctx.save();
    ctx.beginPath();
    ctx.ellipse(0, 0, rx - 2, ry - 2, 0, 0, Math.PI * 2);
    ctx.clip();

    const ix = gazePxX * 0.92;
    const iy = gazePxY * 0.88;
    const irisRx = 72 * scale;
    const irisRy = 77 * scale;

    // Rich layered burgundy-rose anime iris gradient
    const irisGrad = ctx.createRadialGradient(ix, iy + 18, irisRx * 0.12, ix, iy, irisRx);
    if (emotion === 'angry') {
      irisGrad.addColorStop(0, '#FF8DA1');
      irisGrad.addColorStop(0.45, '#B8294B');
      irisGrad.addColorStop(0.82, '#590D22');
      irisGrad.addColorStop(1, '#2B0610');
    } else {
      irisGrad.addColorStop(0, '#F59BB0');
      irisGrad.addColorStop(0.42, '#A8385B');
      irisGrad.addColorStop(0.78, '#541329');
      irisGrad.addColorStop(1, '#280814');
    }
    ctx.fillStyle = irisGrad;
    ctx.beginPath();
    ctx.ellipse(ix, iy, irisRx, irisRy, 0, 0, Math.PI * 2);
    ctx.fill();

    // Lower luminous crescent inside iris
    ctx.strokeStyle = 'rgba(255, 198, 212, 0.55)';
    ctx.lineWidth = 7;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.arc(ix, iy + 4, irisRx * 0.72, 0.22 * Math.PI, 0.78 * Math.PI);
    ctx.stroke();

    // Deep velvet pupil
    const pupScale = emotion === 'sweet' ? 1.12 : (emotion === 'angry' ? 0.86 : 1.0);
    ctx.fillStyle = '#14040A';
    ctx.beginPath();
    ctx.ellipse(ix, iy - 2, 41 * pupScale, 44 * pupScale, 0, 0, Math.PI * 2);
    ctx.fill();

    // Upper iris shadow under lashes
    const topShad = ctx.createLinearGradient(0, -ry, 0, -ry + 46);
    topShad.addColorStop(0, 'rgba(10, 4, 12, 0.58)');
    topShad.addColorStop(1, 'rgba(10, 4, 12, 0)');
    ctx.fillStyle = topShad;
    ctx.fillRect(-rx, -ry, rx * 2, 48);

    // Watery shimmering tear meniscus for sad / cry
    if (emotion === 'sad' || emotion === 'cry') {
      const wobble = Math.sin(tSec * 9 + side) * 2.5;
      const tearGrad = ctx.createLinearGradient(0, ry - 32, 0, ry);
      tearGrad.addColorStop(0, 'rgba(165, 228, 255, 0.15)');
      tearGrad.addColorStop(1, 'rgba(186, 238, 255, 0.85)');
      ctx.fillStyle = tearGrad;
      ctx.beginPath();
      ctx.moveTo(-rx, ry - 18 + wobble);
      ctx.quadraticCurveTo(0, ry - 34 - wobble, rx, ry - 18 + wobble);
      ctx.lineTo(rx, ry + 4);
      ctx.lineTo(-rx, ry + 4);
      ctx.closePath();
      ctx.fill();
    }

    // Specular pearl catchlights (dewy wobble on sweet/cry)
    const specWobble = (emotion === 'sweet' || emotion === 'cry') ? Math.sin(tSec * 10 + side) * 1.8 : 0;
    const hx = ix * 0.55 - 24 + specWobble;
    const hy = iy * 0.55 - 28 - specWobble;
    ctx.fillStyle = '#FFFFFF';
    ctx.beginPath();
    ctx.ellipse(hx, hy, 20, 22, -0.25, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = 'rgba(255, 255, 255, 0.92)';
    ctx.beginPath();
    ctx.arc(ix * 0.55 + 24, iy * 0.55 + 24, 9.5, 0, Math.PI * 2);
    ctx.fill();

    if (emotion === 'sweet' || emotion === 'happy' || emotion === 'proud') {
      ctx.fillStyle = '#FFFBEB';
      drawFourPointStar(ctx, ix * 0.5 + 26, iy * 0.5 - 24, 13 + Math.sin(tSec * 7) * 2.5, 4.2, tSec * 0.8);
    }

    // Angry or sleepy upper eyelid cut inside the eye
    if (emotion === 'angry') {
      ctx.fillStyle = '#18151E';
      ctx.beginPath();
      ctx.moveTo(-rx - 10, -ry - 10);
      ctx.lineTo(rx + 10, -ry - 10);
      if (side < 0) {
        // Viewer-left eye slopes down toward center (+x)
        ctx.lineTo(rx + 10, -ry + 64);
        ctx.lineTo(-rx - 10, -ry + 18);
      } else {
        // Viewer-right eye slopes down toward center (-x)
        ctx.lineTo(rx + 10, -ry + 18);
        ctx.lineTo(-rx - 10, -ry + 64);
      }
      ctx.closePath();
      ctx.fill();
    }

    ctx.restore(); // end eye clip

    // 3. Lush upper lash arch & wing tips (signature Funkira chibi eyelashes!)
    ctx.strokeStyle = '#09080C';
    ctx.lineWidth = 12;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.arc(0, 4, rx + 2, Math.PI * 1.08, Math.PI * 1.92);
    ctx.stroke();

    // Two sculpted outer eyelash flicks on each eye
    ctx.fillStyle = '#09080C';
    ctx.beginPath();
    ctx.moveTo(side * (rx - 10), -ry * 0.42);
    ctx.quadraticCurveTo(side * (rx + 24), -ry * 0.62, side * (rx + 34), -ry * 0.78);
    ctx.quadraticCurveTo(side * (rx + 16), -ry * 0.34, side * (rx - 2), -ry * 0.18);
    ctx.closePath();
    ctx.fill();

    ctx.beginPath();
    ctx.moveTo(side * (rx - 2), -ry * 0.12);
    ctx.quadraticCurveTo(side * (rx + 20), -ry * 0.20, side * (rx + 28), -ry * 0.32);
    ctx.quadraticCurveTo(side * (rx + 12), -ry * 0.02, side * (rx - 4), ry * 0.06);
    ctx.closePath();
    ctx.fill();

    ctx.restore();
  }

  function drawFunkiraBrow(ctx, side, cx, cy, emotion, openMouth) {
    ctx.save();
    let yOff = -openMouth * 7;
    let rot = 0;
    let curve = -18;

    if (emotion === 'happy' || emotion === 'laugh' || emotion === 'proud') {
      yOff -= 12;
      curve = -24;
    } else if (emotion === 'sweet') {
      yOff -= 8;
      rot = side * 0.12;
      curve = -20;
    } else if (emotion === 'angry') {
      yOff += 18;
      rot = -side * 0.38; // sharp inward V-scowl
      curve = -6;
    } else if (emotion === 'sad' || emotion === 'cry') {
      yOff += 6;
      rot = side * 0.30; // worried/sad upturned inner brows
      curve = -8;
    } else if (emotion === 'curious') {
      yOff += (side > 0 ? -18 : 4);
      rot = (side > 0 ? 0.08 : -0.10);
    } else if (emotion === 'sleepy') {
      yOff += 10;
      curve = -10;
    }

    ctx.translate(cx, cy + yOff);
    ctx.rotate(rot);
    ctx.strokeStyle = '#09080C';
    ctx.lineWidth = 10.5;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(-46, 6);
    ctx.quadraticCurveTo(0, curve, 46, 6);
    ctx.stroke();

    // Subtle rose-gold rim glint above brow so it reads crisply even on dark visor glass
    ctx.strokeStyle = 'rgba(229, 137, 158, 0.38)';
    ctx.lineWidth = 3.2;
    ctx.beginPath();
    ctx.moveTo(-38, 1);
    ctx.quadraticCurveTo(0, curve - 4, 38, 1);
    ctx.stroke();
    ctx.restore();
  }

  function drawFunkiraBlushStripes(ctx, cx, cy, alpha) {
    if (alpha <= 0.02) return;
    ctx.save();
    ctx.translate(cx, cy);

    // Soft ambient cheek glow behind //// stripes
    const glow = ctx.createRadialGradient(0, 0, 4, 0, 0, 56);
    glow.addColorStop(0, `rgba(244, 92, 132, ${(alpha * 0.42).toFixed(3)})`);
    glow.addColorStop(1, 'rgba(244, 92, 132, 0)');
    ctx.fillStyle = glow;
    ctx.beginPath();
    ctx.ellipse(0, 0, 56, 26, 0, 0, Math.PI * 2);
    ctx.fill();

    // Signature 4 diagonal pink //// stripes from Alina's Funkira design
    ctx.strokeStyle = `rgba(246, 98, 138, ${alpha.toFixed(3)})`;
    ctx.lineWidth = 5.2;
    ctx.lineCap = 'round';
    for (let i = -1.5; i <= 1.5; i += 1) {
      const sx = i * 16;
      ctx.beginPath();
      ctx.moveTo(sx - 6, 10);
      ctx.lineTo(sx + 7, -10);
      ctx.stroke();
    }
    ctx.restore();
  }

  function drawFunkiraMouthVisor(ctx, openVal, wideVal, userListeningSmile, emotion, tSec) {
    ctx.save();
    ctx.translate(0, 104);

    const open = Math.max(0, Math.min(1, openVal));
    const hw = 48 + 18 * wideVal - 6 * open * (1 - wideVal);

    if (open < 0.035) {
      ctx.strokeStyle = '#09080C';
      ctx.lineWidth = 7.5;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';

      if (emotion === 'angry') {
        // Cute strict pout / firm scowl arch
        ctx.beginPath();
        ctx.moveTo(-36, 14);
        ctx.quadraticCurveTo(0, -6, 36, 14);
        ctx.stroke();
      } else if (emotion === 'sad' || emotion === 'cry') {
        const wob = emotion === 'cry' ? Math.sin(tSec * 22) * 2.2 : 0;
        ctx.beginPath();
        ctx.moveTo(-38, 16 + wob);
        ctx.quadraticCurveTo(0, -8 - wob, 38, 16 - wob);
        ctx.stroke();
      } else if (emotion === 'curious' || emotion === 'sleepy') {
        // Cute little 'o' mouth
        const ry = emotion === 'sleepy' ? 12 + Math.sin(tSec * 2.2) * 4 : 13;
        ctx.fillStyle = '#4A1219';
        ctx.beginPath();
        ctx.ellipse(0, 6, 14, ry, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
      } else if (emotion === 'sweet') {
        // Kawaii :3 cat-smile arch
        ctx.beginPath();
        ctx.moveTo(-40, -2);
        ctx.quadraticCurveTo(-20, 18, 0, 2);
        ctx.quadraticCurveTo(20, 18, 40, -2);
        ctx.stroke();
      } else if (emotion === 'laugh') {
        // Big cheerful open grin even between syllables!
        const traceGrin = () => {
          ctx.beginPath();
          ctx.moveTo(-54, -6);
          ctx.quadraticCurveTo(0, 2, 54, -6);
          ctx.bezierCurveTo(44, 46, -44, 46, -54, -6);
          ctx.closePath();
        };
        traceGrin();
        ctx.fillStyle = '#4A1219';
        ctx.fill();
        ctx.save();
        traceGrin();
        ctx.clip();
        ctx.fillStyle = '#EC6E78';
        ctx.beginPath();
        ctx.ellipse(0, 30, 36, 18, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
        traceGrin();
        ctx.stroke();
      } else {
        // Warm friendly smile
        const sw = hw * (0.84 + userListeningSmile * 0.14);
        const arch = 18 + userListeningSmile * 8 + (emotion === 'happy' || emotion === 'proud' ? 8 : 0);
        ctx.beginPath();
        ctx.moveTo(-sw, -4);
        ctx.quadraticCurveTo(0, arch, sw, -4);
        ctx.stroke();
      }
      ctx.restore();
      return;
    }

    // Active 60-FPS Formant Lip-Sync Mouth (modulated by emotion!)
    const corLift = (emotion === 'angry' || emotion === 'sad' || emotion === 'cry') ? 6 : -5;
    const corY = corLift - wideVal * 4 + open * 3;
    const topMidY = -5 - open * 8;
    const botY = 14 + open * 68;

    const traceMouth = () => {
      ctx.beginPath();
      ctx.moveTo(-hw, corY);
      ctx.quadraticCurveTo(0, topMidY + 6, hw, corY);
      ctx.bezierCurveTo(hw * 0.82, botY, -hw * 0.82, botY, -hw, corY);
      ctx.closePath();
    };

    traceMouth();
    ctx.fillStyle = '#471118';
    ctx.fill();

    ctx.save();
    traceMouth();
    ctx.clip();
    const tongueRx = hw * 0.68;
    const tongueRy = 12 + open * 25;
    const tongueCy = botY - tongueRy * 0.34;
    ctx.fillStyle = '#EC6E78';
    ctx.beginPath();
    ctx.ellipse(2, tongueCy, tongueRx, tongueRy, 0, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = 'rgba(252, 165, 172, 0.45)';
    ctx.beginPath();
    ctx.ellipse(0, tongueCy - tongueRy * 0.22, tongueRx * 0.56, tongueRy * 0.42, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    traceMouth();
    ctx.strokeStyle = '#09080C';
    ctx.lineWidth = 7.2;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.stroke();
    ctx.restore();
  }

  function drawFunkiraVisorFace(ctx, dt, tSec, blinkAmt, userActive) {
    // Transform into Visor Inner Glass Space of 1024x1024 funkira_head.png
    ctx.save();
    ctx.translate(458, 374);
    ctx.rotate(-6.95 * Math.PI / 180);

    // Clip facial elements cleanly inside the rounded dark visor screen
    ctx.save();
    traceRoundedRect(ctx, -352, -211, 704, 422, 36);
    ctx.clip();

    const gazePxX = fkLookX * 26;
    const gazePxY = fkLookY * 16;

    // 1. Pink //// Blush Stripes (intensify on sweet, laugh, happy, angry)
    let blushAlpha = 0.56;
    if (fkEmotion === 'sweet') blushAlpha = 0.98;
    else if (fkEmotion === 'laugh' || fkEmotion === 'happy') blushAlpha = 0.82;
    else if (fkEmotion === 'angry') blushAlpha = 0.72;
    else if (fkEmotion === 'sleepy') blushAlpha = 0.65;
    drawFunkiraBlushStripes(ctx, -182 + gazePxX * 0.16, 94 + gazePxY * 0.12, blushAlpha);
    drawFunkiraBlushStripes(ctx, 182 + gazePxX * 0.16, 94 + gazePxY * 0.12, blushAlpha);

    // 2. Left & Right Expressive Eyes
    drawFunkiraEye(ctx, -1, -166, -12, gazePxX, gazePxY, blinkAmt, fkEmotion, tSec);
    drawFunkiraEye(ctx, 1, 166, -12, gazePxX, gazePxY, blinkAmt, fkEmotion, tSec);

    // 3. Expressive Eyebrows
    drawFunkiraBrow(ctx, -1, -166 + gazePxX * 0.20, -128 + gazePxY * 0.15, fkEmotion, fkMouthOpenSmooth);
    drawFunkiraBrow(ctx, 1, 166 + gazePxX * 0.20, -128 + gazePxY * 0.15, fkEmotion, fkMouthOpenSmooth);

    // 4. Rolling Teardrops for 'cry' emotion
    if (fkEmotion === 'cry') {
      if (Math.random() < dt * 4.5 && fkTearDrops.length < 8) {
        const side = Math.random() < 0.5 ? -1 : 1;
        fkTearDrops.push({
          x: side * 218 + gazePxX * 0.25,
          y: 42 + gazePxY * 0.2,
          vy: 95 + Math.random() * 45,
          vx: side * (12 + Math.random() * 10),
          r: 10 + Math.random() * 4,
          a: 0.95
        });
      }
    }
    for (let i = fkTearDrops.length - 1; i >= 0; i--) {
      const td = fkTearDrops[i];
      td.x += td.vx * dt;
      td.y += td.vy * dt;
      td.vy += 120 * dt;
      td.a -= dt * 0.55;
      if (td.a <= 0.04 || td.y > 210) {
        fkTearDrops.splice(i, 1);
        continue;
      }
      ctx.save();
      ctx.translate(td.x, td.y);
      ctx.fillStyle = `rgba(147, 224, 255, ${td.a.toFixed(3)})`;
      ctx.beginPath();
      ctx.moveTo(0, -td.r * 1.4);
      ctx.quadraticCurveTo(td.r, 0, 0, td.r);
      ctx.quadraticCurveTo(-td.r, 0, 0, -td.r * 1.4);
      ctx.fill();
      ctx.fillStyle = `rgba(255, 255, 255, ${(td.a * 0.85).toFixed(3)})`;
      ctx.beginPath();
      ctx.arc(-td.r * 0.25, td.r * 0.15, td.r * 0.28, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }

    // 5. Emotional & 60-FPS Lip-Sync Mouth
    drawFunkiraMouthVisor(ctx, fkMouthOpenSmooth, fkMouthWideSmooth, userActive, fkEmotion, tSec);

    // 6. Subtle Diagonal Visor Glass Sheen over Face (embeds face organically under glass!)
    const glassSheen = ctx.createLinearGradient(-320, -210, 80, 180);
    glassSheen.addColorStop(0, 'rgba(255, 238, 244, 0.085)');
    glassSheen.addColorStop(0.42, 'rgba(255, 238, 244, 0.02)');
    glassSheen.addColorStop(0.46, 'rgba(255, 238, 244, 0)');
    glassSheen.addColorStop(1, 'rgba(255, 238, 244, 0)');
    ctx.fillStyle = glassSheen;
    ctx.fillRect(-352, -211, 704, 422);

    ctx.restore(); // end visor glass clip

    // 7. Unclipped Emotion Accents on/above Bezel (Angry Cross-Pop, Sweet Sparkle Heart, Sleepy Zzz)
    if (fkEmotion === 'angry') {
      const popScale = 1.0 + Math.sin(tSec * 12) * 0.12;
      ctx.save();
      ctx.translate(282, -168);
      ctx.scale(popScale, popScale);
      ctx.strokeStyle = '#FF4D6D';
      ctx.lineWidth = 7.5;
      ctx.lineCap = 'round';
      for (let q = 0; q < 4; q++) {
        ctx.rotate(Math.PI / 2);
        ctx.beginPath();
        ctx.moveTo(8, 24);
        ctx.quadraticCurveTo(8, 8, 24, 8);
        ctx.stroke();
      }
      ctx.restore();
    } else if (fkEmotion === 'sweet') {
      const floatY = Math.sin(tSec * 4.5) * 8;
      ctx.save();
      ctx.translate(295, -172 + floatY);
      ctx.fillStyle = '#F47296';
      ctx.strokeStyle = '#0B090E';
      ctx.lineWidth = 4;
      ctx.beginPath();
      ctx.moveTo(0, 14);
      ctx.bezierCurveTo(-24, -6, -18, -24, 0, -10);
      ctx.bezierCurveTo(18, -24, 24, -6, 0, 14);
      ctx.fill();
      ctx.stroke();
      ctx.restore();
    } else if (fkEmotion === 'sleepy') {
      ctx.save();
      ctx.font = '800 34px Outfit, sans-serif';
      const zPhase = (tSec * 0.75) % 1;
      ctx.fillStyle = `rgba(253, 230, 138, ${(1 - zPhase).toFixed(3)})`;
      ctx.fillText('z', 270 + zPhase * 22, -170 - zPhase * 42);
      const zPhase2 = ((tSec * 0.75) + 0.5) % 1;
      ctx.font = '800 44px Outfit, sans-serif';
      ctx.fillStyle = `rgba(244, 167, 185, ${(1 - zPhase2).toFixed(3)})`;
      ctx.fillText('Z', 300 + zPhase2 * 28, -185 - zPhase2 * 54);
      ctx.restore();
    }

    ctx.restore();
  }

  // 2-Bone Analytical Inverse Kinematics for Funkira's Left & Right Arms
  function solveArmIK(sx, sy, tx, ty, l1, l2, bendSign) {
    let dx = tx - sx;
    let dy = ty - sy;
    let dist = Math.hypot(dx, dy);
    const maxReach = (l1 + l2) * 0.985;
    const minReach = Math.abs(l1 - l2) + 18;
    if (dist > maxReach) {
      dx = (dx / dist) * maxReach;
      dy = (dy / dist) * maxReach;
      dist = maxReach;
    } else if (dist < minReach) {
      const s = dist > 0.001 ? (minReach / dist) : 1;
      dx *= s;
      dy *= s;
      dist = minReach;
    }

    const cosA = Math.max(-1, Math.min(1, (l1 * l1 + dist * dist - l2 * l2) / (2 * l1 * dist)));
    const baseAngle = Math.atan2(dy, dx);
    const shoulderAngle = baseAngle + bendSign * Math.acos(cosA);
    const ex = sx + Math.cos(shoulderAngle) * l1;
    const ey = sy + Math.sin(shoulderAngle) * l1;
    return {
      sx, sy,
      ex, ey,
      wx: sx + dx,
      wy: sy + dy
    };
  }

  function computeFunkiraArmTargets(tSec) {
    const sinceOpen = (Date.now() - fkStageOpenedAtMs) / 1000;
    let g = fkGesture;
    if (g === 'idle' && sinceOpen < 2.6) {
      g = 'wave';
    }

    // Default relaxed / conversational targets in 1024x1024 rig space
    const talkPulse = fkMouthOpenSmooth > 0.04 ? Math.sin(tSec * 7.2) * 18 * fkMouthOpenSmooth : 0;
    let lTarget = { x: 222, y: 926 - Math.abs(talkPulse) * 0.4, aim: 1.45, pose: 'hip', bend: 1 };
    let rTarget = {
      x: fkMouthOpenSmooth > 0.05 ? 848 + Math.cos(tSec * 5.4) * 18 : 788,
      y: fkMouthOpenSmooth > 0.05 ? 668 - talkPulse : 908,
      aim: fkMouthOpenSmooth > 0.05 ? -1.15 : 1.72,
      pose: fkMouthOpenSmooth > 0.05 ? 'open' : 'hip',
      bend: -1
    };

    if (g === 'wave') {
      const waveSwing = Math.sin(tSec * 8.6) * 42;
      rTarget = { x: 888 + waveSwing, y: 518 + Math.abs(waveSwing) * 0.16, aim: -1.48 + waveSwing * 0.006, pose: 'open', bend: 1 };
    } else if (g === 'point_left') {
      lTarget = { x: -72 + Math.sin(tSec * 4) * 8, y: 662, aim: Math.PI, pose: 'point', bend: 1 };
    } else if (g === 'point_right') {
      rTarget = { x: 1068 + Math.sin(tSec * 4) * 8, y: 632, aim: 0, pose: 'point', bend: 1 };
    } else if (g === 'point_up') {
      rTarget = { x: 878, y: 462 + Math.sin(tSec * 4.5) * 8, aim: -Math.PI * 0.5, pose: 'point', bend: 1 };
    } else if (g === 'point_down') {
      rTarget = { x: 882, y: 968 + Math.sin(tSec * 4.5) * 6, aim: Math.PI * 0.48, pose: 'point', bend: -1 };
    } else if (g === 'point_orders') {
      // Top-right toward 💎 Bestellungen pill
      rTarget = { x: 1012 + Math.sin(tSec * 5) * 8, y: 482 - Math.sin(tSec * 5) * 6, aim: -0.68, pose: 'point', bend: 1 };
    } else if (g === 'point_photo') {
      // Points up-left toward the framed 12-agent group portrait on the back wall
      lTarget = { x: -48 - Math.sin(tSec * 4.5) * 8, y: 472 - Math.sin(tSec * 4.5) * 6, aim: -2.35, pose: 'point', bend: 1 };
    } else if (g === 'point_timer') {
      // Top-left toward Timer pill
      lTarget = { x: -34 - Math.sin(tSec * 5) * 8, y: 504 - Math.sin(tSec * 5) * 6, aim: -2.44, pose: 'point', bend: 1 };
    } else if (g === 'point_camera') {
      // Bottom-right toward Logitech C920 PiP
      rTarget = { x: 1034 + Math.sin(tSec * 4.5) * 7, y: 884 + Math.sin(tSec * 4.5) * 5, aim: 0.58, pose: 'point', bend: -1 };
    } else if (g === 'point_user') {
      // Dynamically points toward where Alina stands in front of the Logitech C920!
      if (fkLookX < -0.18) {
        const px = 60 + fkLookX * 130;
        const py = 685 + fkLookY * 95;
        lTarget = { x: px, y: py, aim: Math.atan2(fkLookY * 90 - 40, fkLookX * 180 - 90), pose: 'point', bend: 1 };
      } else {
        const px = 875 + fkLookX * 155;
        const py = 640 + fkLookY * 95;
        rTarget = { x: px, y: py, aim: Math.atan2(fkLookY * 90 - 45, 70 + fkLookX * 180), pose: 'point', bend: 1 };
      }
    } else if (g === 'hands_on_hips') {
      lTarget = { x: 252, y: 912, aim: 0.92, pose: 'hip', bend: 1 };
      rTarget = { x: 752, y: 892, aim: 2.22, pose: 'hip', bend: -1 };
    } else if (g === 'hands_on_cheeks') {
      const bob = Math.sin(tSec * 5.2) * 5;
      lTarget = { x: 306, y: 572 + bob, aim: -1.18, pose: 'cup', bend: 1 };
      rTarget = { x: 678, y: 538 + bob, aim: -1.96, pose: 'cup', bend: 1 };
    } else if (g === 'cheer') {
      const pump = Math.sin(tSec * 9.5) * 20;
      lTarget = { x: 58, y: 548 + pump, aim: -1.88, pose: 'open', bend: 1 };
      rTarget = { x: 956, y: 492 - pump, aim: -1.26, pose: 'open', bend: 1 };
    } else if (g === 'wipe_tear') {
      const dab = Math.sin(tSec * 7.5) * 16;
      rTarget = { x: 688 + dab, y: 506, aim: -2.15, pose: 'cup', bend: 1 };
    }

    return { lTarget, rTarget };
  }

  function drawArmTubeSegment(ctx, x1, y1, x2, y2, w1, w2, showRibs) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    const len = Math.max(1, Math.hypot(dx, dy));
    const ang = Math.atan2(dy, dx);

    ctx.save();
    ctx.translate(x1, y1);
    ctx.rotate(ang);

    const traceTube = () => {
      ctx.beginPath();
      ctx.moveTo(0, -w1 * 0.5);
      ctx.lineTo(len, -w2 * 0.5);
      ctx.arc(len, 0, w2 * 0.5, -Math.PI * 0.5, Math.PI * 0.5);
      ctx.lineTo(0, w1 * 0.5);
      ctx.arc(0, 0, w1 * 0.5, Math.PI * 0.5, -Math.PI * 0.5);
      ctx.closePath();
    };

    // Metallic dark-charcoal cylindrical shading matching Funkira's torso armor
    const maxW = Math.max(w1, w2);
    const grad = ctx.createLinearGradient(0, -maxW * 0.5, 0, maxW * 0.5);
    grad.addColorStop(0, '#121017');
    grad.addColorStop(0.28, '#383140');
    grad.addColorStop(0.46, '#4B4154');
    grad.addColorStop(0.75, '#1D1924');
    grad.addColorStop(1, '#0D0B11');

    traceTube();
    ctx.fillStyle = grad;
    ctx.fill();

    if (showRibs && len > 55) {
      // Segmented robot sleeve grooves (matches viewer-left arm in reference artwork!)
      ctx.save();
      traceTube();
      ctx.clip();
      ctx.strokeStyle = 'rgba(9, 8, 12, 0.78)';
      ctx.lineWidth = 4.5;
      for (const frac of [0.28, 0.52, 0.76]) {
        const rx = len * frac;
        ctx.beginPath();
        ctx.moveTo(rx - 4, -maxW * 0.55);
        ctx.quadraticCurveTo(rx + 9, 0, rx - 4, maxW * 0.55);
        ctx.stroke();
      }
      ctx.restore();
    }

    traceTube();
    ctx.strokeStyle = '#09080C';
    ctx.lineWidth = 6.2;
    ctx.stroke();

    ctx.restore();
  }

  function drawBossForearmShield(ctx, ex, ey, wx, wy) {
    // Draws the signature ★ BOSS double-chevron shield patch on viewer-right forearm!
    const mx = ex * 0.46 + wx * 0.54;
    const my = ey * 0.46 + wy * 0.54;
    let ang = Math.atan2(wy - ey, wx - ex) + Math.PI * 0.5;
    // Keep shield crest upright-oriented so BOSS text is never upside-down
    while (ang > Math.PI) ang -= Math.PI * 2;
    while (ang < -Math.PI) ang += Math.PI * 2;
    if (Math.abs(ang) > Math.PI * 0.55) {
      ang += ang > 0 ? -Math.PI : Math.PI;
    }
    ang = Math.max(-0.55, Math.min(0.55, ang * 0.65));

    ctx.save();
    ctx.translate(mx, my);
    ctx.rotate(ang);
    ctx.scale(0.86, 0.86);

    const traceShield = (s) => {
      ctx.beginPath();
      ctx.moveTo(0, -40 * s);
      ctx.quadraticCurveTo(36 * s, -36 * s, 38 * s, -22 * s);
      ctx.lineTo(34 * s, 14 * s);
      ctx.quadraticCurveTo(28 * s, 34 * s, 0, 46 * s);
      ctx.quadraticCurveTo(-28 * s, 34 * s, -34 * s, 14 * s);
      ctx.lineTo(-38 * s, -22 * s);
      ctx.quadraticCurveTo(-36 * s, -36 * s, 0, -40 * s);
      ctx.closePath();
    };

    // Outer metallic rose-silver shield rim
    traceShield(1.06);
    ctx.fillStyle = '#09080C';
    ctx.fill();

    traceShield(0.96);
    ctx.strokeStyle = '#E2C4CE';
    ctx.lineWidth = 4.2;
    ctx.fillStyle = '#18151E';
    ctx.fill();
    ctx.stroke();

    // Top 5-point star ★
    ctx.fillStyle = '#F5DCE3';
    ctx.font = '900 15px Outfit, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('★', 0, -21);

    // Bold BOSS lettering
    ctx.fillStyle = '#FFFFFF';
    ctx.font = '900 19px Outfit, sans-serif';
    ctx.fillText('BOSS', 0, -1);

    // Double V-Chevrons below BOSS
    ctx.strokeStyle = '#E8C8D2';
    ctx.lineWidth = 4.0;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    for (const cy of [15, 25]) {
      ctx.beginPath();
      ctx.moveTo(-18, cy - 4);
      ctx.lineTo(0, cy + 7);
      ctx.lineTo(18, cy - 4);
      ctx.stroke();
    }

    ctx.restore();
  }

  function drawRoseGoldCapsuleFinger(ctx, bx, by, angle, length, radius) {
    ctx.save();
    ctx.translate(bx, by);
    ctx.rotate(angle);

    const grad = ctx.createLinearGradient(0, -radius, 0, radius);
    grad.addColorStop(0, '#FCE2E8');
    grad.addColorStop(0.38, '#E6879D');
    grad.addColorStop(0.82, '#9E3E55');
    grad.addColorStop(1, '#6E2537');

    ctx.beginPath();
    ctx.moveTo(0, -radius);
    ctx.lineTo(length, -radius * 0.92);
    ctx.arc(length, 0, radius * 0.92, -Math.PI * 0.5, Math.PI * 0.5);
    ctx.lineTo(0, radius);
    ctx.arc(0, 0, radius, Math.PI * 0.5, -Math.PI * 0.5);
    ctx.closePath();

    ctx.fillStyle = grad;
    ctx.fill();
    ctx.strokeStyle = '#09080C';
    ctx.lineWidth = 4.8;
    ctx.stroke();

    // Robotic knuckle/phalanx ring groove midway along finger
    if (length > 22) {
      ctx.strokeStyle = '#141118';
      ctx.lineWidth = 3.8;
      ctx.beginPath();
      ctx.moveTo(length * 0.52, -radius * 0.88);
      ctx.quadraticCurveTo(length * 0.58, 0, length * 0.52, radius * 0.88);
      ctx.stroke();
    }

    // Glossy fingertip highlight
    ctx.fillStyle = 'rgba(255, 245, 248, 0.58)';
    ctx.beginPath();
    ctx.ellipse(length * 0.82, -radius * 0.30, radius * 0.34, radius * 0.22, 0, 0, Math.PI * 2);
    ctx.fill();

    ctx.restore();
  }

  function drawFunkiraHand(ctx, side, wx, wy, forearmAngle, aimAngle, pose) {
    // side: -1 = viewer-left hand, +1 = viewer-right hand
    ctx.save();
    ctx.translate(wx, wy);

    // 1. Rose-Gold Metallic Wrist Cuff aligned with forearm
    ctx.save();
    ctx.rotate(forearmAngle);
    const cuffGrad = ctx.createLinearGradient(0, -40, 0, 40);
    cuffGrad.addColorStop(0, '#8F394E');
    cuffGrad.addColorStop(0.32, '#F7CBD6');
    cuffGrad.addColorStop(0.62, '#DF7C93');
    cuffGrad.addColorStop(1, '#682233');
    traceRoundedRect(ctx, -16, -39, 28, 78, 12);
    ctx.fillStyle = cuffGrad;
    ctx.fill();
    ctx.strokeStyle = '#09080C';
    ctx.lineWidth = 5.5;
    ctx.stroke();
    ctx.restore();

    // 2. Rotate into Hand Aim Space (x+ points along finger / pointing direction!)
    ctx.rotate(aimAngle);
    const palmOffsetX = 32;

    if (pose === 'open') {
      // Splayed cheerful 5-finger rose-gold chibi robot hand (matches reference artwork!)
      const thumbSign = side > 0 ? -1 : 1;
      drawRoseGoldCapsuleFinger(ctx, palmOffsetX - 6, thumbSign * 34, thumbSign * 0.72, 34, 13.5);
      drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 26, thumbSign * 26, thumbSign * 0.34, 42, 13);
      drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 32, thumbSign * 8, thumbSign * 0.10, 46, 13.5);
      drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 30, -thumbSign * 11, -thumbSign * 0.14, 43, 13);
      drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 22, -thumbSign * 28, -thumbSign * 0.38, 35, 12);
    } else if (pose === 'point') {
      // Bold extended index finger pointing straight along aimAngle, other 3 curled neatly!
      const curlSign = side > 0 ? 1 : -1;
      // Extended pointing index finger
      drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 24, -curlSign * 12, 0, 68, 13.5);
      // Tucked thumb supporting index
      drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 4, -curlSign * 30, -curlSign * 0.45, 32, 13);
      // Curled middle, ring, pinky knuckles
      drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 22, curlSign * 6, curlSign * 0.35, 22, 12.5);
      drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 18, curlSign * 20, curlSign * 0.50, 20, 12);
      drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 12, curlSign * 32, curlSign * 0.65, 17, 11);
    } else if (pose === 'cup') {
      // Softly cupped fingers for hands_on_cheeks / wipe_tear
      for (let i = -1.5; i <= 1.5; i += 1) {
        drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 24, i * 13, i * 0.10, 30, 11.5);
      }
    } else {
      // Relaxed 'hip' pose
      for (let i = -1; i <= 1; i += 1) {
        drawRoseGoldCapsuleFinger(ctx, palmOffsetX + 20, i * 15, i * 0.16, 22, 12);
      }
    }

    // 3. Plump Glossy Rose-Gold Palm Sphere (matches reference image 1:1!)
    const palmGrad = ctx.createRadialGradient(palmOffsetX - 6, -10, 6, palmOffsetX, 0, 44);
    palmGrad.addColorStop(0, '#FFE8EE');
    palmGrad.addColorStop(0.36, '#EA8B9F');
    palmGrad.addColorStop(0.78, '#A9435B');
    palmGrad.addColorStop(1, '#6D2335');

    ctx.beginPath();
    ctx.ellipse(palmOffsetX, 0, 40, 38, 0, 0, Math.PI * 2);
    ctx.fillStyle = palmGrad;
    ctx.fill();
    ctx.strokeStyle = '#09080C';
    ctx.lineWidth = 5.8;
    ctx.stroke();

    // Specular pearl glint on palm dome
    ctx.fillStyle = 'rgba(255, 248, 250, 0.68)';
    ctx.beginPath();
    ctx.ellipse(palmOffsetX - 6, -11, 14, 9, -0.3, 0, Math.PI * 2);
    ctx.fill();

    ctx.restore();
  }

  function drawFunkiraArticulatedArm(ctx, side, ik, aimAngle, pose) {
    // 1. Dark metallic ball-joint inside shoulder socket
    ctx.save();
    ctx.fillStyle = '#18151E';
    ctx.strokeStyle = '#09080C';
    ctx.lineWidth = 5.5;
    ctx.beginPath();
    ctx.arc(ik.sx, ik.sy, 38, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.restore();

    // 2. Segmented Upper Arm
    drawArmTubeSegment(ctx, ik.sx, ik.sy, ik.ex, ik.ey, 72, 64, true);

    // 3. Elbow Joint Sphere with Rose-Gold Accent Ring
    ctx.save();
    ctx.fillStyle = '#241F2C';
    ctx.strokeStyle = '#09080C';
    ctx.lineWidth = 5.5;
    ctx.beginPath();
    ctx.arc(ik.ex, ik.ey, 31, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.strokeStyle = 'rgba(230, 138, 158, 0.55)';
    ctx.lineWidth = 3.2;
    ctx.beginPath();
    ctx.arc(ik.ex, ik.ey, 21, 0, Math.PI * 2);
    ctx.stroke();
    ctx.restore();

    // 4. Forearm Armor Sleeve (+ ★ BOSS Shield on viewer-right arm!)
    drawArmTubeSegment(ctx, ik.ex, ik.ey, ik.wx, ik.wy, 64, 72, side < 0);
    if (side > 0) {
      drawBossForearmShield(ctx, ik.ex, ik.ey, ik.wx, ik.wy);
    }

    // 5. 5-Finger Rose-Gold Robot Hand
    const forearmAngle = Math.atan2(ik.wy - ik.ey, ik.wx - ik.ex);
    drawFunkiraHand(ctx, side, ik.wx, ik.wy, forearmAngle, aimAngle, pose);
  }

  function lerpAngle(a, b, t) {
    let diff = b - a;
    while (diff > Math.PI) diff -= Math.PI * 2;
    while (diff < -Math.PI) diff += Math.PI * 2;
    return a + diff * t;
  }

  function renderFunkiraAvatar(dt, tSec) {
    fkCtx.clearRect(0, 0, 1240, 1040);
    sampleFunkiraVoiceFormants(dt);
    updateFunkiraPersonTracker(dt);

    // Update blink state machine
    if (fkBlinkProgress >= 0) {
      fkBlinkProgress += dt;
      if (fkBlinkProgress >= 0.165) {
        fkBlinkProgress = -1;
        if (fkQueuedDoubleBlink) {
          fkQueuedDoubleBlink = false;
          fkBlinkCountdown = 0.14;
        } else {
          fkBlinkCountdown = (fkEmotion === 'sleepy' ? 1.6 : 2.5) + Math.random() * 3.2;
        }
      }
    } else {
      fkBlinkCountdown -= dt;
      if (fkBlinkCountdown <= 0) {
        fkBlinkProgress = 0;
        fkQueuedDoubleBlink = Math.random() < 0.22;
      }
    }

    const blinkAmt = fkBlinkProgress >= 0
      ? Math.sin((fkBlinkProgress / 0.165) * Math.PI)
      : 0;

    const userActive = (Date.now() - fkLastUserVoiceMs) < 550 ? Math.min(1, fkUserRms * 18) : 0;

    // Emotion-reactive floating & head-tilt physics
    const laughBounce = fkEmotion === 'laugh' ? Math.sin(tSec * 14) * 8 : 0;
    const bobSpeed = fkEmotion === 'sleepy' ? 1.3 : 2.1;
    const bobY = Math.sin(tSec * bobSpeed) * 6.5 - fkMouthOpenSmooth * 2.5 + laughBounce;
    const swayX = Math.cos(tSec * 1.35) * 3.5;

    let baseEmoTilt = 0;
    if (fkEmotion === 'curious') baseEmoTilt = 0.068;
    else if (fkEmotion === 'sweet') baseEmoTilt = -0.045;
    else if (fkEmotion === 'sad' || fkEmotion === 'cry') baseEmoTilt = -0.032;

    const targetTilt = fkToolRunning
      ? -0.028
      : baseEmoTilt + fkLookX * 0.065 + (fkMouthOpenSmooth > 0.05
          ? Math.sin(tSec * 5.2) * 0.018 * (0.4 + fkMouthOpenSmooth)
          : (userActive > 0.1 ? 0.022 : Math.sin(tSec * 1.1) * 0.008));
    fkHeadTiltSmooth += (targetTilt - fkHeadTiltSmooth) * Math.min(1, dt * 7.2);

    const cxStage = 620 + fkBodyFollowX + swayX;
    const cyStage = 556 + bobY;

    // 1. Matte Ambient Aura Ring behind Funkira (reacts to voice & emotion, zero purple!)
    const ringRadius = 335 + fkMouthOpenSmooth * 18 + userActive * 10;
    const auraGrad = fkCtx.createRadialGradient(cxStage, cyStage - 20, 120, cxStage, cyStage - 20, 415);
    if (fkToolRunning) {
      auraGrad.addColorStop(0, 'rgba(16, 185, 129, 0.22)');
      auraGrad.addColorStop(1, 'rgba(16, 185, 129, 0)');
    } else if (fkEmotion === 'angry') {
      auraGrad.addColorStop(0, 'rgba(244, 63, 94, 0.22)');
      auraGrad.addColorStop(1, 'rgba(197, 160, 89, 0)');
    } else if (fkMouthOpenSmooth > 0.04 || fkEmotion === 'sweet' || fkEmotion === 'proud') {
      const a = (0.18 + fkMouthOpenSmooth * 0.22).toFixed(3);
      auraGrad.addColorStop(0, `rgba(232, 146, 164, ${a})`);
      auraGrad.addColorStop(0.6, 'rgba(197, 160, 89, 0.12)');
      auraGrad.addColorStop(1, 'rgba(197, 160, 89, 0)');
    } else {
      auraGrad.addColorStop(0, 'rgba(223, 160, 162, 0.13)');
      auraGrad.addColorStop(1, 'rgba(197, 160, 89, 0)');
    }
    fkCtx.fillStyle = auraGrad;
    fkCtx.beginPath();
    fkCtx.arc(cxStage, cyStage - 20, 415, 0, Math.PI * 2);
    fkCtx.fill();

    fkCtx.save();
    fkCtx.strokeStyle = fkToolRunning
      ? 'rgba(52, 211, 153, 0.45)'
      : (fkEmotion === 'angry'
          ? 'rgba(251, 113, 133, 0.48)'
          : (fkMouthOpenSmooth > 0.04 ? 'rgba(253, 230, 138, 0.44)' : 'rgba(223, 160, 162, 0.25)'));
    fkCtx.lineWidth = 1.6;
    fkCtx.setLineDash([8, 14]);
    fkCtx.translate(cxStage, cyStage - 20);
    fkCtx.rotate(tSec * 0.12);
    fkCtx.beginPath();
    fkCtx.arc(0, 0, ringRadius, 0, Math.PI * 2);
    fkCtx.stroke();
    fkCtx.restore();

    if (!fkSprites.ready) return;

    // 2. Transform into 1024x1024 Articulated Rig Space
    fkCtx.save();
    fkCtx.translate(cxStage, cyStage);
    fkCtx.rotate(fkLookX * 0.024); // subtle torso lean toward person in front of camera!
    const rigScale = 0.72;
    fkCtx.scale(rigScale, rigScale);
    fkCtx.translate(-512, -512);

    // 2a. Draw Torso Armor (FUNKIRA + Mein-Seelenfunke + Neck Post + Rose-Gold Shoulder Rings)
    fkCtx.drawImage(fkSprites.torso, 0, 0, 1024, 1024);

    // 2b. Draw Pivoting Head + Glossy Visor Face + Top Siren Beacon around Neck Pivot (495, 642)
    fkCtx.save();
    const headShiftX = fkLookX * 16;
    const headShiftY = fkLookY * 9;
    fkCtx.translate(495 + headShiftX, 642 + headShiftY);
    fkCtx.rotate(fkHeadTiltSmooth);
    fkCtx.translate(-495, -642);

    fkCtx.drawImage(fkSprites.head, 0, 0, 1024, 1024);

    // Top Rose-Gold Siren Dome Activity & Emotion Glow at (412, 72)
    let beaconAlpha = fkToolRunning
      ? (0.34 + 0.22 * Math.sin(tSec * 8))
      : (fkEmotion === 'angry'
          ? (0.42 + 0.28 * Math.sin(tSec * 10))
          : (fkMouthOpenSmooth > 0.04 ? (0.18 + fkMouthOpenSmooth * 0.32) : (userActive * 0.25)));
    if (beaconAlpha > 0.02) {
      const bg = fkCtx.createRadialGradient(412, 72, 8, 412, 72, 112);
      const col = fkToolRunning
        ? '52, 211, 153'
        : (fkEmotion === 'angry' ? '251, 72, 102' : (fkMouthOpenSmooth > 0.04 ? '253, 230, 138' : '236, 148, 168'));
      bg.addColorStop(0, `rgba(${col}, ${beaconAlpha.toFixed(3)})`);
      bg.addColorStop(1, `rgba(${col}, 0)`);
      fkCtx.fillStyle = bg;
      fkCtx.beginPath();
      fkCtx.arc(412, 72, 112, 0, Math.PI * 2);
      fkCtx.fill();
    }

    // Expressive Visor Eyes, Gaze, Brows, Blush, Tears & Lip-Sync Mouth
    drawFunkiraVisorFace(fkCtx, dt, tSec, blinkAmt, userActive);
    fkCtx.restore(); // end head transform

    // 2c. Update & Draw Both Articulated 2-Bone IK Arms + ★ BOSS Shield + 5-Finger Rose-Gold Hands
    const { lTarget, rTarget } = computeFunkiraArmTargets(tSec);
    const armSmooth = Math.min(1, dt * 8.8);
    fkLeftWrist.x += (lTarget.x - fkLeftWrist.x) * armSmooth;
    fkLeftWrist.y += (lTarget.y - fkLeftWrist.y) * armSmooth;
    fkLeftWrist.aim = lerpAngle(fkLeftWrist.aim, lTarget.aim, armSmooth);

    fkRightWrist.x += (rTarget.x - fkRightWrist.x) * armSmooth;
    fkRightWrist.y += (rTarget.y - fkRightWrist.y) * armSmooth;
    fkRightWrist.aim = lerpAngle(fkRightWrist.aim, rTarget.aim, armSmooth);

    const leftIK = solveArmIK(195, 810, fkLeftWrist.x, fkLeftWrist.y, 138, 152, lTarget.bend);
    const rightIK = solveArmIK(800, 756, fkRightWrist.x, fkRightWrist.y, 138, 152, rTarget.bend);

    drawFunkiraArticulatedArm(fkCtx, -1, leftIK, fkLeftWrist.aim, lTarget.pose);
    drawFunkiraArticulatedArm(fkCtx, 1, rightIK, fkRightWrist.aim, rTarget.pose);

    fkCtx.restore(); // end rig space
  }

  // Magical Light-Dissolve Shutdown Animation ("Magie aktivieren" Sparkle Stream)
  function playMagicDissolveChime() {
    try {
      const ctx = (fkPlayCtx && fkPlayCtx.state !== 'closed') ? fkPlayCtx : ensureAudio();
      if (ctx.state === 'suspended') ctx.resume().catch(() => {});
      const now = ctx.currentTime;
      const notes = [523.25, 659.25, 783.99, 987.77, 1174.66, 1567.98]; // C5 E5 G5 B5 D6 G6
      notes.forEach((freq, i) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = i % 2 === 0 ? 'sine' : 'triangle';
        osc.frequency.setValueAtTime(freq, now + i * 0.068);
        osc.frequency.exponentialRampToValueAtTime(freq * 1.015, now + i * 0.068 + 0.55);
        gain.gain.setValueAtTime(0.0001, now + i * 0.068);
        gain.gain.exponentialRampToValueAtTime(0.085, now + i * 0.068 + 0.025);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.068 + 0.62);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now + i * 0.068);
        osc.stop(now + i * 0.068 + 0.65);
      });
    } catch { }
  }

  function triggerFunkiraMagicDissolve(afterCloseFn, durationSec = 1.15) {
    if (!fkMagicCanvas || !fkMagicCtx || fkMagicDissolveActive) {
      closeFunkiraStageImmediate();
      if (afterCloseFn) afterCloseFn();
      return;
    }

    // Play crystalline golden bell-harp shimmer BEFORE tearing down audio
    playMagicDissolveChime();
    document.body.classList.remove('funkira-open');

    // Compute Funkira center & target ✦ Funkira button screen coordinates
    const avRect = fkCanvas.getBoundingClientRect();
    fkMagicSrc = {
      x: avRect.width > 0 ? avRect.left + avRect.width * 0.5 : window.innerWidth * 0.5,
      y: avRect.height > 0 ? avRect.top + avRect.height * 0.48 : window.innerHeight * 0.46
    };

    const btnEl = document.getElementById('btn-open-funkira');
    const btnRect = btnEl ? btnEl.getBoundingClientRect() : null;
    fkMagicDst = (btnRect && btnRect.width > 0)
      ? { x: btnRect.left + btnRect.width * 0.5, y: btnRect.top + btnRect.height * 0.5 }
      : { x: window.innerWidth - 220, y: 34 };

    // Spawn 185 golden & rose-gold magic light particles
    const palette = ['#FDE68A', '#C5A059', '#F4A7B9', '#FFFBEB', '#E88B9C'];
    fkMagicParticles = Array.from({ length: 185 }, (_, i) => {
      const ang = (i / 185) * Math.PI * 2 + (Math.random() - 0.5) * 0.4;
      const rad = 18 + Math.pow(Math.random(), 0.65) * 195;
      const sx = fkMagicSrc.x + Math.cos(ang) * rad;
      const sy = fkMagicSrc.y + Math.sin(ang) * rad * 0.92;
      const swirl = (i % 2 === 0 ? 1 : -1) * (90 + Math.random() * 170);
      return {
        sx, sy,
        cx: (sx + fkMagicDst.x) * 0.5 + swirl,
        cy: Math.min(sy, fkMagicDst.y) - 60 - Math.random() * 160,
        delay: Math.random() * 0.24,
        speed: 0.85 + Math.random() * 0.35,
        size: 3.5 + Math.random() * 9.5,
        isStar: i % 3 === 0,
        color: palette[i % palette.length],
        rot: Math.random() * Math.PI * 2
      };
    });

    fkMagicElapsed = 0;
    fkMagicDuration = durationSec;
    fkMagicAbsorbTriggered = false;
    fkMagicDoneCallback = afterCloseFn || null;
    fkMagicDissolveActive = true;
    fkMagicCanvas.classList.remove('hidden');

    // Start stage magic-dissolving CSS transition & release camera/mic privacy lock!
    const stageEl = document.getElementById('funkira-stage');
    stageEl.classList.add('magic-dissolving');
    releaseFunkiraSessionAndHardware();

    if (afterCloseFn) {
      afterCloseFn();
    }

    setTimeout(() => {
      funkiraStageOpen = false;
      stageEl.classList.add('hidden');
      stageEl.classList.remove('magic-dissolving');
      syncAtelierVideoPlayback();
      // Re-measure button in case workspace switched underneath
      const br = btnEl ? btnEl.getBoundingClientRect() : null;
      if (br && br.width > 0) {
        fkMagicDst = { x: br.left + br.width * 0.5, y: br.top + br.height * 0.5 };
      }
    }, 390);
  }

  function renderFunkiraMagicSparks(dt, tSec) {
    if (!fkMagicDissolveActive || !fkMagicCtx) return;
    const w = window.innerWidth;
    const h = window.innerHeight;
    fkMagicCtx.clearRect(0, 0, w, h);

    fkMagicElapsed += dt;
    const norm = fkMagicElapsed / fkMagicDuration;

    // Initial radiant golden-rose flash core at Funkira's position
    if (norm < 0.48) {
      const flashA = Math.sin((norm / 0.48) * Math.PI) * 0.72;
      const rCore = 70 + norm * 290;
      const g = fkMagicCtx.createRadialGradient(fkMagicSrc.x, fkMagicSrc.y, 8, fkMagicSrc.x, fkMagicSrc.y, rCore);
      g.addColorStop(0, `rgba(255, 251, 235, ${flashA.toFixed(3)})`);
      g.addColorStop(0.38, `rgba(244, 167, 185, ${(flashA * 0.75).toFixed(3)})`);
      g.addColorStop(0.72, `rgba(197, 160, 89, ${(flashA * 0.42).toFixed(3)})`);
      g.addColorStop(1, 'rgba(197, 160, 89, 0)');
      fkMagicCtx.fillStyle = g;
      fkMagicCtx.beginPath();
      fkMagicCtx.arc(fkMagicSrc.x, fkMagicSrc.y, rCore, 0, Math.PI * 2);
      fkMagicCtx.fill();
    }

    let alive = 0;
    for (const p of fkMagicParticles) {
      const pt = (norm - p.delay) * p.speed / 0.78;
      if (pt < 0) {
        alive++;
        continue;
      }
      if (pt >= 1.0) continue;
      alive++;

      // Smooth cubic ease-in-out along quadratic Bezier arc into ✦ Funkira button
      const u = pt < 0.5 ? 4 * pt * pt * pt : 1 - Math.pow(-2 * pt + 2, 3) / 2;
      const inv = 1 - u;
      const x = inv * inv * p.sx + 2 * inv * u * p.cx + u * u * fkMagicDst.x;
      const y = inv * inv * p.sy + 2 * inv * u * p.cy + u * u * fkMagicDst.y;
      const alpha = Math.sin(Math.min(1, pt) * Math.PI);
      const sz = p.size * (0.45 + 0.75 * alpha);

      fkMagicCtx.save();
      fkMagicCtx.globalAlpha = Math.max(0, Math.min(1, alpha));
      fkMagicCtx.fillStyle = p.color;
      if (p.isStar) {
        drawFourPointStar(fkMagicCtx, x, y, sz * 1.45, sz * 0.38, p.rot + tSec * 4.5);
      } else {
        fkMagicCtx.beginPath();
        fkMagicCtx.arc(x, y, sz * 0.62, 0, Math.PI * 2);
        fkMagicCtx.fill();
      }
      fkMagicCtx.restore();
    }

    if (!fkMagicAbsorbTriggered && norm >= 0.62) {
      fkMagicAbsorbTriggered = true;
      const btnEl = document.getElementById('btn-open-funkira');
      if (btnEl) {
        btnEl.classList.remove('magic-absorb');
        void btnEl.offsetWidth;
        btnEl.classList.add('magic-absorb');
        setTimeout(() => btnEl.classList.remove('magic-absorb'), 800);
      }
    }

    if (norm >= 1.08 || alive === 0) {
      fkMagicDissolveActive = false;
      fkMagicCtx.clearRect(0, 0, w, h);
      fkMagicCanvas.classList.add('hidden');
      fkMagicDoneCallback = null;
    }
  }

  requestAnimationFrame(renderLoop);

  // =========================================================================
  // 2c. FUNKIRA LIVE WEBSOCKET, LOGITECH C920 VISION & ECHO-FREE AUDIO
  // =========================================================================
  function setFunkiraStatusUi(text, dotState) {
    document.getElementById('funkira-state-label').textContent = text;
    const dot = document.getElementById('funkira-state-dot');
    dot.classList.toggle('offline', dotState === 'offline');
  }

  function isFunkiraOutputProtected() {
    const now = Date.now();
    const hasSources = fkActiveSources.length > 0;
    const reverbTail = (now - fkLastSpeechEndMs) < 750;
    const aiGenerating = fkIsAiTurn && !fkGenComplete && (now - fkLastAiPacketMs) < 3400;
    return hasSources || reverbTail || aiGenerating;
  }

  function stopFunkiraAudioPlayback() {
    if (fkTurnDrainTimeout) {
      clearTimeout(fkTurnDrainTimeout);
      fkTurnDrainTimeout = null;
    }
    for (const src of fkActiveSources) {
      try { src.onended = null; src.stop(); src.disconnect(); } catch { }
    }
    fkActiveSources = [];
    fkNextPlayTime = 0;
    fkIsSpeaking = false;
    fkIsAiTurn = false;
    fkGenComplete = false;
    fkLastSpeechEndMs = Date.now();
  }

  async function refreshFunkiraAudioSinkBinding() {
    try {
      if (audioCtx && audioCtx.state !== 'closed' && typeof audioCtx.setSinkId === 'function') {
        await audioCtx.setSinkId('');
      }
    } catch { }
    if (!fkPlayCtx || fkPlayCtx.state === 'closed') return;
    try {
      if (typeof fkPlayCtx.setSinkId === 'function') {
        await fkPlayCtx.setSinkId('');
      }
      if (fkPlayCtx.state === 'suspended') {
        await fkPlayCtx.resume();
      }
    } catch { }
  }

  function flushPendingUiAfterSpeech() {
    if (!fkPendingUiAfterSpeech) return;
    const cmd = fkPendingUiAfterSpeech;
    fkPendingUiAfterSpeech = null;
    if (fkPendingUiSafetyTimer) {
      clearTimeout(fkPendingUiSafetyTimer);
      fkPendingUiSafetyTimer = null;
    }
    // Natural 280 ms breath pause after Funkira's final spoken syllable before dissolving into magic light!
    setTimeout(() => {
      executeImmediateMiniPcUiAction(cmd);
    }, 280);
  }

  function playFunkiraPcm24k(base64Data) {
    if (!fkPlayCtx || fkPlayCtx.state === 'closed' || !base64Data) return;
    if (fkPlayCtx.state === 'suspended') {
      fkPlayCtx.resume().catch(() => {});
    }
    if (fkTurnDrainTimeout) {
      clearTimeout(fkTurnDrainTimeout);
      fkTurnDrainTimeout = null;
    }

    let bin;
    try {
      bin = window.atob(base64Data);
    } catch {
      return;
    }
    const byteLen = bin.length - (bin.length % 2);
    if (byteLen <= 0) return;

    fkIsSpeaking = true;
    fkIsAiTurn = true;
    fkLastAiPacketMs = Date.now();
    setFunkiraStatusUi('Funkira spricht · Werkstatt Mini-PC', 'online');

    const bytes = new Uint8Array(byteLen);
    for (let i = 0; i < byteLen; i++) bytes[i] = bin.charCodeAt(i);

    const pcm16 = new Int16Array(bytes.buffer, 0, byteLen / 2);
    const f32 = new Float32Array(pcm16.length);
    for (let i = 0; i < pcm16.length; i++) f32[i] = pcm16[i] / 32768.0;

    const buf = fkPlayCtx.createBuffer(1, f32.length, 24000);
    buf.getChannelData(0).set(f32);

    const src = fkPlayCtx.createBufferSource();
    src.buffer = buf;
    // Direct hardware path (GainNode -> destination) + parallel tap into AnalyserNode for lip-sync
    src.connect(fkPlayGain || fkPlayCtx.destination);

    if (fkNextPlayTime < fkPlayCtx.currentTime) {
      fkNextPlayTime = fkPlayCtx.currentTime + 0.035;
    }

    fkActiveSources.push(src);
    src.start(fkNextPlayTime);
    fkNextPlayTime += buf.duration;

    src.onended = () => {
      fkActiveSources = fkActiveSources.filter(s => s !== src);
      fkLastSpeechEndMs = Date.now();
      if (fkActiveSources.length === 0) {
        const finishTurn = () => {
          fkNextPlayTime = 0;
          fkIsSpeaking = false;
          fkIsAiTurn = false;
          fkGenComplete = false;
          fkLastInteractionMs = Date.now();
          if (fkIdlePromptAsked && fkIdlePromptFinishMs === 0) {
            fkIdlePromptFinishMs = Date.now();
          }
          if (funkiraStageOpen && !fkIdleAtelierMode) {
            setFunkiraStatusUi(`Funkira hört zu · Atelier Stufe ${fkLatestShopLevel}`, 'online');
          }
          flushPendingUiAfterSpeech();
        };

        if (fkGenComplete) {
          finishTurn();
        } else {
          fkTurnDrainTimeout = setTimeout(() => {
            if (fkActiveSources.length === 0) finishTurn();
          }, 1300);
        }
      }
    };
  }

  async function initFunkiraCameraAndMic() {
    // 1. Unified AudioContext (Voice Output + Lip-Sync Analyser + Mic Input on ONE PipeWire Stream)
    fkPlayCtx = new (window.AudioContext || window.webkitAudioContext)();
    if (typeof fkPlayCtx.setSinkId === 'function') {
      await fkPlayCtx.setSinkId('').catch(() => {});
    }
    fkPlayGain = fkPlayCtx.createGain();
    fkPlayGain.gain.value = 1.42;
    fkPlayGain.connect(fkPlayCtx.destination);

    fkPlayAnalyser = fkPlayCtx.createAnalyser();
    fkPlayAnalyser.fftSize = 256;
    fkPlayAnalyser.smoothingTimeConstant = 0.52;
    fkPlayGain.connect(fkPlayAnalyser);
    fkFreqBins = new Uint8Array(fkPlayAnalyser.frequencyBinCount);
    fkTimeBins = new Uint8Array(fkPlayAnalyser.fftSize);
    if (fkPlayCtx.state === 'suspended') await fkPlayCtx.resume().catch(() => {});

    // 2. Select Logitech HD Pro Webcam C920 explicitly if present
    let preferredVideoId = undefined;
    let preferredAudioId = undefined;
    try {
      const devs = await navigator.mediaDevices.enumerateDevices();
      const cam = devs.find(d => d.kind === 'videoinput' && /c920|logitech|webcam/i.test(d.label))
               || devs.find(d => d.kind === 'videoinput');
      const mic = devs.find(d => d.kind === 'audioinput' && /c920|logitech|webcam|usb/i.test(d.label))
               || devs.find(d => d.kind === 'audioinput' && d.deviceId !== 'default');
      if (cam?.deviceId) preferredVideoId = cam.deviceId;
      if (mic?.deviceId) preferredAudioId = mic.deviceId;
    } catch { }

    const videoConstraints = preferredVideoId
      ? { deviceId: { exact: preferredVideoId }, width: { ideal: 640 }, height: { ideal: 360 }, frameRate: { ideal: 15, max: 15 } }
      : { width: { ideal: 640 }, height: { ideal: 360 }, frameRate: { ideal: 15, max: 15 } };

    const audioConstraints = {
      ...(preferredAudioId ? { deviceId: { exact: preferredAudioId } } : {}),
      channelCount: { ideal: 1 },
      echoCancellation: { ideal: true },
      noiseSuppression: { ideal: true },
      autoGainControl: { ideal: true }
    };

    try {
      fkMediaStream = await navigator.mediaDevices.getUserMedia({ video: videoConstraints, audio: audioConstraints });
    } catch {
      try {
        fkMediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: { echoCancellation: true } });
      } catch {
        fkMediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      }
    }

    const vidEl = document.getElementById('funkira-cam-video');
    if (fkMediaStream.getVideoTracks().length > 0) {
      vidEl.srcObject = fkMediaStream;
      const vLabel = fkMediaStream.getVideoTracks()[0].label || 'Logitech C920';
      document.getElementById('funkira-eye-label').textContent = vLabel.replace(/\s*\([0-9a-f]{4}:[0-9a-f]{4}\)\s*/i, '');
      document.getElementById('funkira-eye-dot').classList.remove('off');
      document.getElementById('funkira-eye-pip').classList.remove('cam-off');
    } else {
      document.getElementById('funkira-eye-label').textContent = 'Kamera nicht bereit';
      document.getElementById('funkira-eye-dot').classList.add('off');
    }

    // 3. 16 kHz Microphone Capture Pipeline on the SAME Unified AudioContext (keeps A2DP sink warm & bound!)
    if (fkPlayCtx.state === 'suspended') await fkPlayCtx.resume().catch(() => {});

    fkMicSource = fkPlayCtx.createMediaStreamSource(fkMediaStream);
    fkMicProcessor = fkPlayCtx.createScriptProcessor(4096, 1, 1);

    fkMicProcessor.onaudioprocess = (e) => {
      if (e.outputBuffer) {
        for (let ch = 0; ch < e.outputBuffer.numberOfChannels; ch++) {
          e.outputBuffer.getChannelData(ch).fill(0);
        }
      }
      if (!fkPlayCtx || !fkWs || fkWs.readyState !== WebSocket.OPEN || !fkSetupComplete) return;
      if (fkMicMuted || fkToolRunning || fkIdleAtelierMode) {
        fkUserRms = 0;
        fkBargeConsecHits = 0;
        return;
      }

      const rawInput = e.inputBuffer.getChannelData(0);
      let sumSq = 0;
      for (let i = 0; i < rawInput.length; i++) sumSq += rawInput[i] * rawInput[i];
      const rms = Math.sqrt(sumSq / rawInput.length);
      fkUserRms = rms;

      // Encode 16 kHz PCM frame once (used both for live stream & pre-barge buffer)
      const encodeFrameToBase64 = (floatSamples) => {
        let samples = floatSamples;
        if (fkPlayCtx.sampleRate !== 16000) {
          const ratio = fkPlayCtx.sampleRate / 16000;
          const outLen = Math.round(samples.length / ratio);
          const resampled = new Float32Array(outLen);
          for (let i = 0; i < outLen; i++) {
            const pos = i * ratio;
            const idx = Math.floor(pos);
            const frac = pos - idx;
            resampled[i] = (idx + 1 < samples.length) ? samples[idx] * (1 - frac) + samples[idx + 1] * frac : samples[idx];
          }
          samples = resampled;
        }
        const pcm16 = new Int16Array(samples.length);
        for (let i = 0; i < samples.length; i++) {
          const s = Math.max(-1, Math.min(1, samples[i]));
          pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
        }
        const bytes = new Uint8Array(pcm16.buffer);
        let bin = '';
        for (let i = 0; i < bytes.byteLength; i++) bin += String.fromCharCode(bytes[i]);
        return window.btoa(bin);
      };

      // REAL-TIME BARGE-IN: Allow Alina to interrupt Funkira mid-sentence without speaker self-echo!
      if (isFunkiraOutputProtected()) {
        const bargeThreshold = Math.max(0.036, fkCurrentOutputRms * 0.34);
        const frameB64 = encodeFrameToBase64(rawInput);
        fkPreBargePcmQueue.push(frameB64);
        if (fkPreBargePcmQueue.length > 2) fkPreBargePcmQueue.shift();

        if (rms > bargeThreshold) {
          fkBargeConsecHits++;
          if (fkBargeConsecHits >= 2) {
            fkBargeConsecHits = 0;
            stopFunkiraAudioPlayback();
            fkLastSpeechEndMs = 0;
            fkPendingUiAfterSpeech = null;
            if (fkPendingUiSafetyTimer) { clearTimeout(fkPendingUiSafetyTimer); fkPendingUiSafetyTimer = null; }
            fkLastUserVoiceMs = Date.now();
            fkLastInteractionMs = Date.now();
            fkIdlePromptAsked = false;
            fkIdlePromptFinishMs = 0;
            fkHasSpokenInTurn = true;
            setFunkiraExpress('curious', 'idle', 3.2, false);
            setFunkiraStatusUi('Du sprichst · Funkira hört zu', 'online');
            while (fkPreBargePcmQueue.length > 0) {
              const queuedB64 = fkPreBargePcmQueue.shift();
              fkWs.send(JSON.stringify({
                realtimeInput: { audio: { mimeType: 'audio/pcm;rate=16000', data: queuedB64 } }
              }));
            }
          }
        } else {
          fkBargeConsecHits = Math.max(0, fkBargeConsecHits - 1);
        }
        return;
      }

      fkBargeConsecHits = 0;
      fkPreBargePcmQueue.length = 0;

      let input = rawInput;
      if (rms > 0.011) {
        fkLastUserVoiceMs = Date.now();
        fkLastInteractionMs = Date.now();
        fkIdlePromptAsked = false;
        fkIdlePromptFinishMs = 0;
        fkHasSpokenInTurn = true;
      } else if (fkHasSpokenInTurn && (Date.now() - fkLastUserVoiceMs) > 260) {
        input = new Float32Array(rawInput.length);
      }

      const b64Payload = encodeFrameToBase64(input);
      fkWs.send(JSON.stringify({
        realtimeInput: {
          audio: {
            mimeType: 'audio/pcm;rate=16000',
            data: b64Payload
          }
        }
      }));
    };

    fkMicSource.connect(fkMicProcessor);
    fkMicProcessor.connect(fkPlayCtx.destination);

    const encodeCanvasJpegAsync = (canvas, quality) => new Promise((resolve) => {
      canvas.toBlob((blob) => {
        if (!blob) { resolve(''); return; }
        const rdr = new FileReader();
        rdr.onloadend = () => {
          const res = typeof rdr.result === 'string' ? rdr.result : '';
          const comma = res.indexOf(',');
          resolve(comma >= 0 ? res.slice(comma + 1) : '');
        };
        rdr.onerror = () => resolve('');
        rdr.readAsDataURL(blob);
      }, 'image/jpeg', quality);
    });

    // 4. Async Off-Thread Vision Stream from Logitech C920 to Funkira (0.0 ms main-thread block!)
    let camEncodeBusy = false;
    if (fkCamInterval) clearInterval(fkCamInterval);
    fkCamInterval = setInterval(async () => {
      if (camEncodeBusy || !funkiraStageOpen || fkIdleAtelierMode || fkTikTokLiveMode || !fkCamEnabled || !fkSetupComplete || !fkWs || fkWs.readyState !== WebSocket.OPEN) return;
      if (vidEl.videoWidth > 0 && vidEl.videoHeight > 0) {
        camEncodeBusy = true;
        try {
          const w = Math.min(640, vidEl.videoWidth);
          const h = Math.round(vidEl.videoHeight * (w / vidEl.videoWidth));
          fkSnapCanvas.width = w;
          fkSnapCanvas.height = h;
          fkSnapCtx.drawImage(vidEl, 0, 0, w, h);
          const b64 = await encodeCanvasJpegAsync(fkSnapCanvas, 0.58);
          if (b64 && fkWs && fkWs.readyState === WebSocket.OPEN) {
            fkWs.send(JSON.stringify({
              realtimeInput: {
                video: {
                  mimeType: 'image/jpeg',
                  data: b64
                }
              }
            }));
          }
        } finally {
          camEncodeBusy = false;
        }
      }
    }, 1250);

    // 5. Async Off-Thread Local YuNet + SFace Owner vs. Visitor Face Check (100% Loopback RAM, 0 Cloud!)
    let faceCheckBusy = false;
    if (fkFaceCheckInterval) clearInterval(fkFaceCheckInterval);
    fkFaceCheckInterval = setInterval(async () => {
      if (faceCheckBusy || !funkiraStageOpen || fkIdleAtelierMode || fkTikTokLiveMode || !fkCamEnabled) return;
      if (!vidEl || vidEl.videoWidth <= 0 || vidEl.videoHeight <= 0) return;
      faceCheckBusy = true;
      try {
        const w = Math.min(480, vidEl.videoWidth);
        const h = Math.round(vidEl.videoHeight * (w / vidEl.videoWidth));
        fkSnapCanvas.width = w;
        fkSnapCanvas.height = h;
        fkSnapCtx.drawImage(vidEl, 0, 0, w, h);
        const b64 = await encodeCanvasJpegAsync(fkSnapCanvas, 0.68);
        if (!b64) return;
        const res = await fetch('/api/funkira/face-check', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ data: b64 })
        });
        const out = await res.json();
        if (typeof out.ownerEnrolled === 'boolean') fkOwnerFaceEnrolled = out.ownerEnrolled;
        if (out.effectiveIdentity) fkFaceIdentity = out.effectiveIdentity;
        updateFaceAndTikTokBadges();
      } catch { } finally {
        faceCheckBusy = false;
      }
    }, 4500);
  }

  function captureHighResVideoJpegBase64() {
    const vidEl = document.getElementById('funkira-cam-video');
    if (!vidEl || vidEl.videoWidth <= 0 || vidEl.videoHeight <= 0) return '';
    fkSnapCanvas.width = vidEl.videoWidth;
    fkSnapCanvas.height = vidEl.videoHeight;
    fkSnapCtx.drawImage(vidEl, 0, 0, vidEl.videoWidth, vidEl.videoHeight);
    return fkSnapCanvas.toDataURL('image/jpeg', 0.92).split(',')[1] || '';
  }

  function executeImmediateMiniPcUiAction(cmd) {
    const act = cmd.action || '';
    const useMagic = !!cmd.magicExit && funkiraStageOpen;

    if (act === 'show_orders' || act === 'close_funkira') {
      if (useMagic) {
        triggerFunkiraMagicDissolve(() => switchWorkspace('gewerbe'), 1.15);
      } else {
        closeFunkiraStageImmediate();
        switchWorkspace('gewerbe');
      }
    } else if (act === 'focus_on') {
      if (useMagic) {
        triggerFunkiraMagicDissolve(() => { switchWorkspace('gewerbe'); toggleFocusMode(true); }, 1.0);
      } else {
        closeFunkiraStageImmediate();
        switchWorkspace('gewerbe');
        toggleFocusMode(true);
      }
    } else if (act === 'focus_off') {
      toggleFocusMode(false);
    } else if (act === 'open_desktop') {
      closeFunkiraStageImmediate();
      switchWorkspace('desktop');
    } else if (act === 'open_app') {
      closeFunkiraStageImmediate();
      switchWorkspace('desktop');
      if (cmd.app) launchApp(cmd.app);
    } else if (act === 'wallpaper_video') {
      setWallpaperMode('AtelierVideo');
    } else if (act === 'wallpaper_kosmos') {
      setWallpaperMode('Kosmos');
    } else if (act === 'test_order_celebration') {
      closeFunkiraStageImmediate();
      switchWorkspace('gewerbe');
      triggerOrderCelebration();
    }
  }

  function handleLocalFunkiraCommand(data) {
    if (data.command === 'avatarExpress') {
      setFunkiraExpress(data.emotion || 'happy', data.gesture || 'idle', data.durationSeconds || 6, true);
      return;
    }
    if (data.command === 'refreshTray') {
      refreshFunkiraAudioSinkBinding();
      pollSystemTray();
      return;
    }
    if (data.command === 'testChime') {
      playOrderChime();
      return;
    }
    if (data.command === 'showSnapshotLightbox' && data.fullPath) {
      openLightbox([{ fullPath: data.fullPath, name: data.fileName || 'Kamerabild' }], 0);
      return;
    }
    if (data.command === 'openDocumentLightbox' && data.fullPath) {
      openLightbox([{ fullPath: data.fullPath, name: data.fileName || data.relPath || 'Dokument' }], 0);
      return;
    }
    if (data.command === 'timer') {
      workshopTimerActive = !!data.active;
      workshopTimerLabel = data.label || 'Timer';
      workshopTimerRemain = data.seconds || 0;
      updateWorkshopTimerDom();
      return;
    }
    if (data.command === 'ui') {
      if (data.farewellWave) {
        setFunkiraExpress('happy', 'wave', 14, true);
      }
      if (data.waitForSpeechFinish) {
        fkPendingUiAfterSpeech = data;
        if (fkPendingUiSafetyTimer) clearTimeout(fkPendingUiSafetyTimer);
        // Safety fallback only if Gemini finishes without emitting audio chunks
        fkPendingUiSafetyTimer = setTimeout(() => {
          if (fkPendingUiAfterSpeech === data && !fkIsSpeaking && fkActiveSources.length === 0) {
            flushPendingUiAfterSpeech();
          }
        }, 7500);
      } else {
        executeImmediateMiniPcUiAction(data);
      }
    }
  }

  let fkCurrentAiText = '';
  let fkCurrentUserText = '';

  function updateFaceAndTikTokBadges() {
    const faceBtn = document.getElementById('btn-funkira-face');
    if (faceBtn) {
      faceBtn.classList.remove('face-owner', 'face-visitor');
      if (!fkOwnerFaceEnrolled) {
        faceBtn.textContent = '👑 Gesicht einrichten';
      } else if (fkFaceIdentity === 'visitor') {
        faceBtn.textContent = '🛡 Besucher-Schutz';
        faceBtn.classList.add('face-visitor');
      } else {
        faceBtn.textContent = '👑 Alina erkannt';
        faceBtn.classList.add('face-owner');
      }
    }

    const ttBtn = document.getElementById('btn-funkira-tiktok');
    const ttBadge = document.getElementById('tiktok-live-badge');
    const ttRoses = document.getElementById('tiktok-roses-count');
    const eyePip = document.getElementById('funkira-eye-pip');
    if (ttBtn) {
      ttBtn.classList.toggle('tiktok-active', fkTikTokLiveMode);
      ttBtn.textContent = fkTikTokLiveMode ? '🎥 TikTok aktiv' : '🎥 TikTok Live';
    }
    if (ttBadge) ttBadge.classList.toggle('hidden', !fkTikTokLiveMode);
    if (ttRoses) ttRoses.textContent = String(fkTikTokRosesCount || 0);
    if (eyePip) eyePip.classList.toggle('hidden', fkTikTokLiveMode);
  }

  function releaseBrowserMediaOnly() {
    fkSetupComplete = false;
    fkToolRunning = false;
    if (fkPingInterval) { clearInterval(fkPingInterval); fkPingInterval = null; }
    if (fkCamInterval) { clearInterval(fkCamInterval); fkCamInterval = null; }
    if (fkFaceCheckInterval) { clearInterval(fkFaceCheckInterval); fkFaceCheckInterval = null; }

    if (fkWs) {
      fkWs.onclose = null;
      fkWs.onerror = null;
      try { fkWs.close(1000, 'idle_or_switch'); } catch { }
      fkWs = null;
    }

    stopFunkiraAudioPlayback();

    if (fkMediaStream) {
      fkMediaStream.getTracks().forEach(t => { try { t.stop(); } catch { } });
      fkMediaStream = null;
    }
    const vidEl = document.getElementById('funkira-cam-video');
    if (vidEl) vidEl.srcObject = null;
    document.getElementById('funkira-eye-target')?.classList.add('hidden');

    if (fkMicSource) { try { fkMicSource.disconnect(); } catch { } fkMicSource = null; }
    if (fkMicProcessor) { try { fkMicProcessor.disconnect(); } catch { } fkMicProcessor = null; }
    if (fkPlayGain) { try { fkPlayGain.disconnect(); } catch { } fkPlayGain = null; }
    if (fkPlayAnalyser) { try { fkPlayAnalyser.disconnect(); } catch { } fkPlayAnalyser = null; }
    if (fkPlayCtx) {
      const ctxToClose = fkPlayCtx;
      fkPlayCtx = null;
      setTimeout(() => { try { ctxToClose.close(); } catch { } }, 450);
    }
  }

  async function enterFunkiraZeroTokenAtelierMode() {
    if (!funkiraStageOpen || fkIdleAtelierMode || fkTikTokLiveMode) return;
    fkIdleAtelierMode = true;
    fkIdlePromptAsked = false;
    fkIdlePromptFinishMs = 0;

    // 1. Close Gemini Live WebSocket & release mic so PipeWire hands C920 cleanly to offline Vosk (0 Cloud Tokens!)
    releaseBrowserMediaOnly();

    // 2. Send Funkira walking into her 3D Atelier to sweep, polish blanks & inspect xTool
    if (window.Funkira3D) {
      window.Funkira3D.setMode('atelier_autonomous');
    }

    const wsBtn = document.getElementById('btn-funkira-wake-sleep');
    if (wsBtn) wsBtn.textContent = '👋 Herrufen';
    setFunkiraStatusUi('✦ 0-Token Werkel-Modus · Ruf „Funkira“', 'online');
    document.getElementById('funkira-ai-line').textContent = 'Ich werkle hinten im 3D-Atelier (0 Token). Ruf einfach „Funkira“ oder klicke ins Bild!';

    // 3. Arm local offline German Vosk wake-word listener & poll loopback
    setTimeout(async () => {
      if (!funkiraStageOpen || !fkIdleAtelierMode) return;
      try {
        await fetch('/api/funkira/wake-mode', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ active: true })
        });
      } catch { }

      if (fkWakePollInterval) clearInterval(fkWakePollInterval);
      fkWakePollInterval = setInterval(async () => {
        if (!funkiraStageOpen || !fkIdleAtelierMode || fkWakeTransitionBusy) return;
        try {
          const r = await fetch('/api/funkira/wake-poll');
          const d = await r.json();
          if (d && d.triggered) {
            wakeFunkiraFromAtelier('wake_word');
          }
        } catch { }
      }, 450);
    }, 520);
  }

  async function wakeFunkiraFromAtelier(reason = 'wake_word', pendingTextPrompt = '') {
    if (!funkiraStageOpen || fkWakeTransitionBusy) return;
    fkWakeTransitionBusy = true;
    try {
      if (fkWakePollInterval) { clearInterval(fkWakePollInterval); fkWakePollInterval = null; }
      try {
        await fetch('/api/funkira/wake-mode', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ active: false })
        });
      } catch { }

      fkIdleAtelierMode = false;
      fkIdlePromptAsked = false;
      fkIdlePromptFinishMs = 0;
      fkLastInteractionMs = Date.now();

      const wsBtn = document.getElementById('btn-funkira-wake-sleep');
      if (wsBtn) wsBtn.textContent = '🧹 Werkeln lassen';

      if (window.Funkira3D) {
        window.Funkira3D.runToCamera();
      }
      setFunkiraStatusUi('Funkira kommt nach vorne...', 'online');
      document.getElementById('funkira-ai-line').textContent = 'Bin schon auf dem Weg nach vorne, Alina!';

      await new Promise(res => setTimeout(res, 220));
      try {
        await initFunkiraCameraAndMic();
      } catch { }

      connectFunkiraLiveWebSocket(reason, pendingTextPrompt);
    } finally {
      fkWakeTransitionBusy = false;
    }
  }

  function connectFunkiraLiveWebSocket(initialReason = '', pendingTextPrompt = '') {
    const wsProto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    fkWs = new WebSocket(`${wsProto}//${window.location.host}/ws/funkira-live`);

    fkWs.onopen = () => {
      if (fkPingInterval) clearInterval(fkPingInterval);
      fkPingInterval = setInterval(() => {
        if (fkWs && fkWs.readyState === WebSocket.OPEN) {
          fkWs.send(JSON.stringify({ type: 'ping' }));
        }
      }, 10000);
    };

    fkWs.onmessage = (ev) => {
      let data;
      try { data = JSON.parse(ev.data); } catch { return; }

      if (data.type === 'funkiraAuthRequired') {
        closeFunkiraStageImmediate();
        pendingOpenFunkiraAfterLogin = true;
        document.getElementById('login-status-msg').textContent = data.message || 'Bitte anmelden.';
        document.getElementById('modal-overlay').classList.remove('hidden');
        return;
      }

      if (data.type === 'funkiraError') {
        setFunkiraStatusUi('Verbindungsfehler', 'offline');
        document.getElementById('funkira-ai-line').textContent = data.message || 'Verbindungsfehler.';
        return;
      }

      if (data.type === 'funkiraSessionInfo') {
        if (data.cameraName) {
          document.getElementById('funkira-eye-label').textContent = data.cameraName;
        }
        if (typeof data.tikTokLiveMode === 'boolean') fkTikTokLiveMode = data.tikTokLiveMode;
        if (typeof data.tikTokHandle === 'string') fkTikTokHandle = data.tikTokHandle;
        if (data.faceIdentity) fkFaceIdentity = data.faceIdentity;
        if (data.worldState) {
          fkLatestShopLevel = data.worldState.shopLevel || fkLatestShopLevel;
          if (typeof data.worldState.rosesCount === 'number') fkTikTokRosesCount = data.worldState.rosesCount;
          window.Funkira3D?.setWorldState(data.worldState);
        }
        updateFaceAndTikTokBadges();
        return;
      }

      if (data.type === 'funkiraTikTokGift') {
        fkTikTokRosesCount = data.totalRoses ?? (fkTikTokRosesCount + (data.count || 1));
        updateFaceAndTikTokBadges();
        window.Funkira3D?.triggerTikTokGift(data.count || 1);
        return;
      }

      if (data.type === 'requestHighResSnapshot') {
        const b64 = captureHighResVideoJpegBase64();
        if (fkWs && fkWs.readyState === WebSocket.OPEN) {
          fkWs.send(JSON.stringify({
            type: 'highResSnapshotResponse',
            reqId: data.reqId,
            data: b64
          }));
        }
        return;
      }

      if (data.type === 'funkiraToolStart') {
        fkToolRunning = true;
        fkLastInteractionMs = Date.now();
        const names = (data.tools || []).join(', ');
        document.getElementById('funkira-tool-text').textContent = `⚙ ${names}`;
        document.getElementById('funkira-tool-pill').classList.remove('hidden');
        return;
      }

      if (data.type === 'funkiraToolDone') {
        fkToolRunning = false;
        fkLastInteractionMs = Date.now();
        document.getElementById('funkira-tool-pill').classList.add('hidden');
        refreshFunkiraAudioSinkBinding();
        pollPortalPulse();
        return;
      }

      if (data.type === 'funkiraLocalCommand') {
        handleLocalFunkiraCommand(data);
        return;
      }

      if (data.setupComplete) {
        fkSetupComplete = true;
        fkSetupCompleteAtMs = Date.now();
        fkLastInteractionMs = Date.now();
        refreshFunkiraAudioSinkBinding();
        setFunkiraStatusUi(`Funkira hört zu · Atelier Stufe ${fkLatestShopLevel}`, 'online');

        if (pendingTextPrompt) {
          fkProactiveSessionSent = true;
          fkWs.send(JSON.stringify({ realtimeInput: { text: pendingTextPrompt } }));
        } else if (initialReason === 'wake_word') {
          fkProactiveSessionSent = true;
          fkWs.send(JSON.stringify({ type: 'personPresenceEvent', reason: 'wake_word', position: 'vor dem Monitor' }));
        } else {
          document.getElementById('funkira-ai-line').textContent = fkTikTokLiveMode
            ? 'TikTok-Live-Sandbox aktiv (keine internen Kundendaten). Ich bin bereit für den Stream!'
            : 'Ich bin vorne in meinem 3D-Atelier bereit, Alina. Du kannst mich jederzeit unterbrechen!';
        }
        return;
      }

      if (data.serverContent) {
        const sc = data.serverContent;
        if (sc.interrupted) {
          stopFunkiraAudioPlayback();
          fkLastSpeechEndMs = 0;
          fkPendingUiAfterSpeech = null;
          setFunkiraStatusUi(`Funkira hört zu · Atelier Stufe ${fkLatestShopLevel}`, 'online');
        }

        const uTxt = sc.inputTranscription?.text || sc.inputAudioTranscription?.text || '';
        if (uTxt) {
          fkLastInteractionMs = Date.now();
          fkIdlePromptAsked = false;
          fkIdlePromptFinishMs = 0;
          fkCurrentUserText = (fkCurrentUserText + ' ' + uTxt).replace(/\s+/g, ' ').trim();
          const uEl = document.getElementById('funkira-user-line');
          uEl.textContent = `Du: „${fkCurrentUserText}“`;
          uEl.classList.remove('hidden');
          inferFunkiraExpressFromText(fkCurrentUserText, true);
        }

        const aTxt = sc.outputTranscription?.text || sc.outputAudioTranscription?.text || '';
        if (aTxt) {
          if (!fkIsAiTurn) fkCurrentAiText = '';
          fkCurrentAiText += aTxt;
          document.getElementById('funkira-ai-line').textContent = fkCurrentAiText.trim();
          const box = document.getElementById('funkira-transcript-box');
          box.scrollTop = box.scrollHeight;
          inferFunkiraExpressFromText(aTxt, false);
        }

        if (sc.modelTurn) {
          if (!fkIsAiTurn) fkCurrentAiText = '';
          fkHasSpokenInTurn = false;
          const parts = sc.modelTurn.parts || [];
          for (const p of parts) {
            const audioB64 = p.inlineData?.data || p.inline_data?.data;
            if (audioB64) {
              playFunkiraPcm24k(audioB64);
            }
          }
        }

        if (sc.turnComplete || sc.generationComplete) {
          fkGenComplete = true;
          fkCurrentUserText = '';
          if (fkActiveSources.length === 0) {
            fkIsSpeaking = false;
            fkIsAiTurn = false;
            fkLastInteractionMs = Date.now();
            if (fkIdlePromptAsked && fkIdlePromptFinishMs === 0) {
              fkIdlePromptFinishMs = Date.now();
            }
            if (!fkIdleAtelierMode) {
              setFunkiraStatusUi(`Funkira hört zu · Atelier Stufe ${fkLatestShopLevel}`, 'online');
            }
            flushPendingUiAfterSpeech();
          }
        }
      }
    };

    fkWs.onclose = () => {
      if (funkiraStageOpen && !fkIdleAtelierMode) {
        setFunkiraStatusUi('Verbindung getrennt', 'offline');
      }
    };
  }

  async function openFunkiraStage() {
    if (funkiraStageOpen || fkMagicDissolveActive) return;

    try {
      const stRes = await fetch('/api/funkira/status');
      const st = await stRes.json();
      if (!st.hasToken) {
        pendingOpenFunkiraAfterLogin = true;
        document.getElementById('login-status-msg').textContent = 'Einmalig anmelden, um Funkira & Bestell-Monitor zu verbinden.';
        document.getElementById('modal-overlay').classList.remove('hidden');
        return;
      }
    } catch { }

    try {
      await fetch('/api/funkira/wake-mode', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ active: false })
      });
    } catch { }

    funkiraStageOpen = true;
    fkStageOpenedAtMs = Date.now();
    fkLastInteractionMs = Date.now();
    fkIdleAtelierMode = false;
    fkIdlePromptAsked = false;
    fkIdlePromptFinishMs = 0;
    fkSetupComplete = false;
    fkSetupCompleteAtMs = 0;
    fkProactiveSessionSent = false;
    fkPersonDetected = false;
    fkPersonConsecHits = 0;
    fkPersonWasAway = false;
    fkPrevLuma = null;
    fkTearDrops = [];
    fkMicMuted = false;
    fkCamEnabled = true;
    fkCurrentAiText = '';
    fkCurrentUserText = '';
    fkPendingUiAfterSpeech = null;

    const stageEl = document.getElementById('funkira-stage');
    stageEl.classList.remove('magic-dissolving');
    stageEl.classList.remove('hidden');
    document.body.classList.add('funkira-open');
    syncAtelierVideoPlayback();

    const c3d = document.getElementById('funkira-3d-canvas');
    if (window.Funkira3D && c3d) {
      window.Funkira3D.init(c3d);
      window.Funkira3D.setMode('front_active');
      window.Funkira3D.start();
      window.Funkira3D.resize();
    }

    const hr = new Date().getHours();
    setFunkiraExpress((hr >= 22 || hr < 6) ? 'sleepy' : 'happy', 'wave', 3.8, false);

    document.getElementById('funkira-eye-target')?.classList.add('hidden');
    document.getElementById('btn-funkira-mic-toggle').textContent = '🎙 Ohr aktiv';
    document.getElementById('btn-funkira-cam-toggle').textContent = '👁 Auge aktiv';
    document.getElementById('btn-funkira-wake-sleep').textContent = '🧹 Werkeln lassen';
    document.getElementById('funkira-user-line').classList.add('hidden');
    document.getElementById('funkira-ai-line').textContent = 'Verbinde mit Funkira im 3D-Atelier...';
    document.getElementById('funkira-tool-pill').classList.add('hidden');
    updateFaceAndTikTokBadges();
    setFunkiraStatusUi('Verbinde Funkira...', 'offline');

    // Start 1-Minute Idle Token-Saver Watchdog
    if (fkIdleMonitorInterval) clearInterval(fkIdleMonitorInterval);
    fkIdleMonitorInterval = setInterval(() => {
      if (!funkiraStageOpen || fkIdleAtelierMode || fkTikTokLiveMode || !fkSetupComplete) return;
      if (fkIsSpeaking || fkIsAiTurn || fkActiveSources.length > 0 || fkToolRunning) return;

      const now = Date.now();
      if (!fkIdlePromptAsked && (now - fkLastInteractionMs) >= 60000) {
        fkIdlePromptAsked = true;
        fkIdlePromptSentAtMs = now;
        fkIdlePromptFinishMs = 0;
        if (fkWs && fkWs.readyState === WebSocket.OPEN) {
          fkWs.send(JSON.stringify({ type: 'personPresenceEvent', reason: 'idle_check', position: 'vor dem Monitor' }));
        }
        return;
      }

      if (fkIdlePromptAsked) {
        const refMs = fkIdlePromptFinishMs > 0 ? fkIdlePromptFinishMs : (fkIdlePromptSentAtMs + 8000);
        if ((now - refMs) >= 13500 && fkLastUserVoiceMs < fkIdlePromptSentAtMs) {
          enterFunkiraZeroTokenAtelierMode();
        }
      }
    }, 1000);

    try {
      await initFunkiraCameraAndMic();
    } catch (camErr) {
      document.getElementById('funkira-ai-line').textContent = 'Kamera/Mikrofon Hinweis: ' + (camErr.message || camErr);
    }

    connectFunkiraLiveWebSocket('', '');
  }

  function releaseFunkiraSessionAndHardware() {
    fkIdleAtelierMode = false;
    fkIdlePromptAsked = false;
    fkPendingUiAfterSpeech = null;
    if (fkPendingUiSafetyTimer) { clearTimeout(fkPendingUiSafetyTimer); fkPendingUiSafetyTimer = null; }
    if (fkIdleMonitorInterval) { clearInterval(fkIdleMonitorInterval); fkIdleMonitorInterval = null; }
    if (fkWakePollInterval) { clearInterval(fkWakePollInterval); fkWakePollInterval = null; }

    fetch('/api/funkira/wake-mode', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ active: false })
    }).catch(() => {});

    releaseBrowserMediaOnly();
    window.Funkira3D?.stop();
  }

  function closeFunkiraStageImmediate() {
    funkiraStageOpen = false;
    document.body.classList.remove('funkira-open');
    releaseFunkiraSessionAndHardware();
    const stageEl = document.getElementById('funkira-stage');
    stageEl.classList.add('hidden');
    stageEl.classList.remove('magic-dissolving');
    syncAtelierVideoPlayback();
  }

  function closeFunkiraStage() {
    if (!funkiraStageOpen) return;
    triggerFunkiraMagicDissolve(null, 0.82);
  }

  async function sendFunkiraTextPrompt() {
    const inp = document.getElementById('funkira-text-input');
    const txt = inp.value.trim();
    if (!txt) return;
    inp.value = '';

    fkLastInteractionMs = Date.now();
    fkIdlePromptAsked = false;
    fkIdlePromptFinishMs = 0;

    const uEl = document.getElementById('funkira-user-line');
    uEl.textContent = `Du: „${txt}“`;
    uEl.classList.remove('hidden');
    fkCurrentAiText = '';
    fkProactiveSessionSent = true;
    inferFunkiraExpressFromText(txt, true);

    if (fkIdleAtelierMode) {
      await wakeFunkiraFromAtelier('text_wake', txt);
      return;
    }

    if (fkWs && fkWs.readyState === WebSocket.OPEN && fkSetupComplete) {
      stopFunkiraAudioPlayback();
      fkWs.send(JSON.stringify({
        realtimeInput: {
          text: txt
        }
      }));
      return;
    }

    // REST Chat Fallback
    document.getElementById('funkira-ai-line').textContent = 'Funkira denkt nach...';
    try {
      const res = await fetch('/api/funkira/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ prompt: txt })
      });
      const data = await res.json();
      const replyText = data.response || data.message || 'Bereit.';
      document.getElementById('funkira-ai-line').textContent = replyText;
      inferFunkiraExpressFromText(replyText, false);
      if (data.audio) {
        playFunkiraPcm24k(data.audio);
      }
    } catch (e) {
      document.getElementById('funkira-ai-line').textContent = 'Netzwerkfehler: ' + e.message;
    }
  }

  document.getElementById('btn-open-funkira').addEventListener('click', openFunkiraStage);
  document.getElementById('btn-taskbar-funkira').addEventListener('click', openFunkiraStage);
  document.getElementById('btn-funkira-close').addEventListener('click', closeFunkiraStage);
  document.getElementById('btn-funkira-send').addEventListener('click', sendFunkiraTextPrompt);
  document.getElementById('funkira-text-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') sendFunkiraTextPrompt();
  });

  document.getElementById('btn-funkira-wake-sleep')?.addEventListener('click', () => {
    if (fkIdleAtelierMode) {
      wakeFunkiraFromAtelier('wake_word');
    } else {
      enterFunkiraZeroTokenAtelierMode();
    }
  });

  document.getElementById('funkira-3d-canvas')?.addEventListener('click', () => {
    if (fkIdleAtelierMode) {
      wakeFunkiraFromAtelier('wake_word');
    } else {
      fkLastInteractionMs = Date.now();
      setFunkiraExpress('sweet', 'wave', 3.5, false);
    }
  });

  // Offline Face Guard Modal Handlers
  document.getElementById('btn-funkira-face')?.addEventListener('click', () => {
    const st = document.getElementById('face-modal-status');
    st.textContent = fkOwnerFaceEnrolled
      ? `Aktueller Status: ${fkFaceIdentity === 'visitor' ? 'Besucher-Schutz aktiv (interne Daten gesperrt)' : 'Alina verifiziert (128-Float AES-256-GCM lokal)'}.`
      : 'Noch kein Chefin-Gesicht gespeichert. Schau kurz in die Logitech C920 und klicke auf Einlernen.';
    document.getElementById('face-modal-overlay')?.classList.remove('hidden');
  });
  document.getElementById('btn-close-face-modal')?.addEventListener('click', () => {
    document.getElementById('face-modal-overlay')?.classList.add('hidden');
  });
  document.getElementById('btn-face-enroll')?.addEventListener('click', async () => {
    const st = document.getElementById('face-modal-status');
    const b64 = captureHighResVideoJpegBase64();
    if (!b64) {
      st.textContent = 'Kamera ist nicht aktiv. Bitte zuerst Auge aktivieren.';
      return;
    }
    st.textContent = 'Berechne lokalen 128-Float Gesichtsvektor...';
    try {
      const res = await fetch('/api/funkira/face-enroll', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ data: b64 })
      });
      const out = await res.json();
      st.textContent = out.message || (out.ok ? 'Gespeichert!' : 'Fehler.');
      if (out.ok) {
        fkOwnerFaceEnrolled = true;
        fkFaceIdentity = 'alina';
        updateFaceAndTikTokBadges();
      }
    } catch (e) {
      st.textContent = 'Fehler: ' + e.message;
    }
  });
  document.getElementById('btn-face-clear')?.addEventListener('click', async () => {
    await fetch('/api/funkira/face-clear', { method: 'POST' }).catch(() => {});
    fkOwnerFaceEnrolled = false;
    fkFaceIdentity = 'unenrolled';
    updateFaceAndTikTokBadges();
    document.getElementById('face-modal-status').textContent = 'Lokaler Gesichtsvektor gelöscht.';
  });

  // TikTok Live Sandbox Modal Handlers
  document.getElementById('btn-funkira-tiktok')?.addEventListener('click', () => {
    document.getElementById('tiktok-handle-input').value = fkTikTokHandle || '@meinseelenfunke';
    document.getElementById('tiktok-modal-overlay')?.classList.remove('hidden');
  });
  document.getElementById('btn-close-tiktok-modal')?.addEventListener('click', () => {
    document.getElementById('tiktok-modal-overlay')?.classList.add('hidden');
  });
  async function applyTikTokSandboxMode(enable) {
    const handle = document.getElementById('tiktok-handle-input').value.trim();
    const res = await fetch('/api/funkira/tiktok-mode', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ enabled: enable, handle })
    });
    const out = await res.json();
    fkTikTokLiveMode = !!out.tikTokLiveMode;
    fkTikTokHandle = out.tikTokHandle || '';
    updateFaceAndTikTokBadges();
    document.getElementById('tiktok-modal-overlay')?.classList.add('hidden');

    if (funkiraStageOpen) {
      if (fkIdleAtelierMode) {
        await wakeFunkiraFromAtelier('');
      } else {
        releaseBrowserMediaOnly();
        try { await initFunkiraCameraAndMic(); } catch { }
        connectFunkiraLiveWebSocket('', '');
      }
    }
  }
  document.getElementById('btn-tiktok-enable')?.addEventListener('click', () => applyTikTokSandboxMode(true));
  document.getElementById('btn-tiktok-disable')?.addEventListener('click', () => applyTikTokSandboxMode(false));
  document.getElementById('btn-tiktok-test-rose')?.addEventListener('click', async () => {
    const sender = document.getElementById('tiktok-test-sender').value.trim() || 'Sarah';
    const res = await fetch('/api/funkira/tiktok-gift', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ senderName: sender, giftName: 'Rose', count: 1 })
    });
    const out = await res.json();
    if (out.ok && !fkWs) {
      fkTikTokRosesCount = out.totalRoses || (fkTikTokRosesCount + 1);
      updateFaceAndTikTokBadges();
      window.Funkira3D?.triggerTikTokGift(1);
    }
    document.getElementById('tiktok-modal-status').textContent = `Rose von „${out.senderFirst || sender}“ in goldene Werkbank-Vase gelegt (Gesamt: ${out.totalRoses || 1}).`;
  });

  document.getElementById('funkira-eye-pip').addEventListener('click', (e) => {
    e.currentTarget.classList.toggle('expanded');
  });

  document.getElementById('btn-funkira-mic-toggle').addEventListener('click', (e) => {
    fkMicMuted = !fkMicMuted;
    e.currentTarget.textContent = fkMicMuted ? '🔇 Ohr stumm' : '🎙 Ohr aktiv';
  });

  document.getElementById('btn-funkira-cam-toggle').addEventListener('click', (e) => {
    fkCamEnabled = !fkCamEnabled;
    if (fkMediaStream) {
      fkMediaStream.getVideoTracks().forEach(t => { t.enabled = fkCamEnabled; });
    }
    e.currentTarget.textContent = fkCamEnabled ? '👁 Auge aktiv' : '🚫 Auge pausiert';
    document.getElementById('funkira-eye-pip').classList.toggle('cam-off', !fkCamEnabled);
    document.getElementById('funkira-eye-dot').classList.toggle('off', !fkCamEnabled);
  });

  document.getElementById('btn-funkira-snap').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const b64 = captureHighResVideoJpegBase64();
    if (!b64) return;
    btn.textContent = '📸 Speichere...';
    try {
      const res = await fetch('/api/funkira/snapshot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ data: b64, folder: 'manufaktur', label: 'Werkstatt' })
      });
      const out = await res.json();
      if (out.ok && out.fullPath) {
        openLightbox([{ fullPath: out.fullPath, name: out.fileName }], 0);
      }
    } catch { }
    btn.textContent = '📷 Foto';
  });

  // =========================================================================
  // 3. FOCUS MODE & KEYBOARD SHORTCUTS (F = Fokus, K = Funkira, Esc = Close)
  // =========================================================================
  let idleTimer = null;
  function resetMouseIdle() {
    document.body.classList.remove('mouse-idle');
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
      if (document.body.classList.contains('focus-mode')) {
        document.body.classList.add('mouse-idle');
      }
    }, 3000);
  }

  async function toggleFocusMode(forceVal) {
    const next = typeof forceVal === 'boolean' ? forceVal : !document.body.classList.contains('focus-mode');
    document.body.classList.toggle('focus-mode', next);
    resetMouseIdle();
    syncAtelierVideoPlayback();
    try {
      await fetch('/api/settings/focus', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ focusMode: next })
      });
    } catch { }
  }

  document.getElementById('btn-toggle-focus').addEventListener('click', () => toggleFocusMode());
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const faceOv = document.getElementById('face-modal-overlay');
      if (faceOv && !faceOv.classList.contains('hidden')) {
        faceOv.classList.add('hidden');
        return;
      }
      const ttOv = document.getElementById('tiktok-modal-overlay');
      if (ttOv && !ttOv.classList.contains('hidden')) {
        ttOv.classList.add('hidden');
        return;
      }
      if (!document.getElementById('lightbox-overlay').classList.contains('hidden')) {
        document.getElementById('lightbox-overlay').classList.add('hidden');
        return;
      }
      if (funkiraStageOpen) {
        closeFunkiraStage();
        return;
      }
    }
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'f' || e.key === 'F') toggleFocusMode();
    if (e.key === 'k' || e.key === 'K') {
      if (funkiraStageOpen) closeFunkiraStage();
      else openFunkiraStage();
    }
  });

  // =========================================================================
  // 4. PASSWORD EYE TOGGLE (Rule 3.2: Always Eye Icon on Password Inputs)
  // =========================================================================
  document.addEventListener('click', (e) => {
    const eyeBtn = e.target.closest('[data-toggle-pw]');
    if (!eyeBtn) return;
    const id = eyeBtn.getAttribute('data-toggle-pw');
    const inp = document.getElementById(id);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
  });

  // =========================================================================
  // 5. LIVE PORTAL PULSE & CLOCK SYNC
  // =========================================================================
  let focusInitApplied = false;

  async function pollPortalPulse() {
    try {
      const res = await fetch('/api/pulse');
      const data = await res.json();
      if (!focusInitApplied) {
        focusInitApplied = true;
        if (data.focusMode) document.body.classList.add('focus-mode');
      }
      if (typeof data.hasAtelierVideo === 'boolean') {
        serverHasAtelierVideo = data.hasAtelierVideo;
      }
      if (data.wallpaperMode) {
        currentWallpaperMode = data.wallpaperMode;
      }
      syncAtelierVideoPlayback();

      if (data.workshopTimer) {
        workshopTimerActive = !!data.workshopTimer.active;
        if (workshopTimerActive) {
          workshopTimerLabel = data.workshopTimer.label || 'Timer';
          workshopTimerRemain = data.workshopTimer.remainSec || 0;
        }
        updateWorkshopTimerDom();
      }

      const p = data.pulse;
      document.getElementById('orb-orders-count').textContent = p.openOrdersCount ?? 0;
      document.getElementById('metric-open-carts').textContent = p.openCartsCount ?? 0;

      // Bold Gold Active Routine Banner Centered Below Golden Crystal Orb (Visible in Normal & Focus Mode!)
      const routineBannerEl = document.getElementById('gewerbe-routine-banner');
      if (routineBannerEl) {
        const cleanBanner = (p.activeRoutineBanner || '').replace(/\s[–—]\s/g, ' · ').trim();
        routineBannerEl.textContent = cleanBanner;
      }

      if (typeof data.tikTokLiveMode === 'boolean') fkTikTokLiveMode = data.tikTokLiveMode;
      if (typeof data.tikTokHandle === 'string') fkTikTokHandle = data.tikTokHandle;
      if (typeof data.tikTokRosesCount === 'number') fkTikTokRosesCount = data.tikTokRosesCount;
      if (typeof data.ownerFaceEnrolled === 'boolean') fkOwnerFaceEnrolled = data.ownerFaceEnrolled;
      if (data.faceIdentity) fkFaceIdentity = data.faceIdentity;
      if (p.shopLevel) fkLatestShopLevel = p.shopLevel;
      updateFaceAndTikTokBadges();

      if (window.Funkira3D) {
        window.Funkira3D.setWorldState({
          shopLevel: p.shopLevel || 1,
          season: p.season || 'autumn',
          occasion: p.occasion || 'none',
          rosesCount: fkTikTokRosesCount
        });
      }

      const topCustPill = document.getElementById('pill-top-customers');
      const topCustTxt = document.getElementById('metric-top-customers');
      if (p.topCustomersGdpr && p.topCustomersGdpr.length > 0) {
        topCustTxt.textContent = p.topCustomersGdpr.join(' · ');
        topCustPill.classList.remove('hidden');
      } else {
        topCustPill.classList.add('hidden');
      }

      const dot = document.getElementById('pulse-status-dot');
      const stTxt = document.getElementById('pulse-status-text');
      const loginBtn = document.getElementById('btn-portal-login');

      dot.classList.toggle('offline', !p.isConnected);
      stTxt.textContent = p.statusSummary || 'Offline';
      loginBtn.textContent = p.isConnected ? `✓ ${p.environment}` : '🔗 Verbinden';
      loginBtn.classList.toggle('gold-primary', !p.isConnected);

      if (p.newOrderJustArrived) {
        triggerOrderCelebration();
      }
    } catch { }
  }

  let cachedTray = null;
  async function pollSystemTray() {
    try {
      const res = await fetch('/api/system/tray');
      cachedTray = await res.json();
      const volStr = cachedTray.audio.isMuted ? 'Stumm' : `${cachedTray.audio.volume}%`;
      document.getElementById('gewerbe-vol-label').textContent = volStr;
      document.getElementById('tray-vol-lbl').textContent = volStr;
      document.getElementById('tray-wifi-lbl').textContent = cachedTray.connectedWifi
        ? `${cachedTray.connectedWifi} (${cachedTray.connectedWifiBand})`
        : 'WLAN';
      document.getElementById('tray-bt-lbl').textContent = cachedTray.connectedBt || 'Bluetooth';
      document.getElementById('gewerbe-clock').textContent = cachedTray.timeNow;
      document.getElementById('tray-clock-lbl').textContent = cachedTray.timeNow;
    } catch { }
  }

  pollPortalPulse();
  pollSystemTray();
  setInterval(pollPortalPulse, 4000);
  setInterval(pollSystemTray, 5000);

  for (const id of ['btn-gewerbe-sound', 'tray-vol-btn']) {
    document.getElementById(id).addEventListener('wheel', async (e) => {
      e.preventDefault();
      const cur = cachedTray?.audio?.volume ?? 65;
      const next = Math.max(0, Math.min(100, cur + (e.deltaY < 0 ? 2 : -2)));
      await fetch('/api/audio/volume', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ volume: next })
      });
      pollSystemTray();
    });
  }

  // =========================================================================
  // 6. TOPBAR ACTIONS (TEST, NIGHT SLEEP 08:00, PORTAL LOGIN, WORKSPACE)
  // =========================================================================
  document.getElementById('btn-test-chime').addEventListener('click', () => triggerOrderCelebration());

  document.getElementById('btn-night-sleep').addEventListener('click', async () => {
    if (!confirm('Mini-PC jetzt in die Nachtruhe versetzen und um Punkt 08:00 Uhr automatisch aufwecken?')) return;
    await fetch('/api/power/night-sleep', { method: 'POST' });
  });

  document.getElementById('btn-open-browser-quick').addEventListener('click', () => {
    fetch('/api/browser/open', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url: 'https://mein-seelenfunke.de' })
    });
  });

  function switchWorkspace(ws) {
    activeWorkspace = ws;
    document.getElementById('gewerbe-workspace').classList.toggle('hidden', ws !== 'gewerbe');
    document.getElementById('desktop-workspace').classList.toggle('hidden', ws !== 'desktop');
    hidePopovers();
    syncAtelierVideoPlayback();
  }

  document.getElementById('btn-to-desktop').addEventListener('click', () => switchWorkspace('desktop'));
  document.getElementById('btn-back-gewerbe').addEventListener('click', () => switchWorkspace('gewerbe'));

  const modalOverlay = document.getElementById('modal-overlay');
  document.getElementById('btn-portal-login').addEventListener('click', () => {
    pendingOpenFunkiraAfterLogin = false;
    modalOverlay.classList.remove('hidden');
  });
  document.getElementById('btn-close-modal').addEventListener('click', () => {
    pendingOpenFunkiraAfterLogin = false;
    modalOverlay.classList.add('hidden');
  });

  document.getElementById('btn-submit-login').addEventListener('click', async () => {
    const statusEl = document.getElementById('login-status-msg');
    statusEl.textContent = 'Verbinde...';
    const res = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        env: document.getElementById('login-env').value,
        email: document.getElementById('login-email').value,
        password: document.getElementById('login-password').value,
        twoFactorCode: document.getElementById('login-2fa').value
      })
    });
    const out = await res.json();
    if (out.twoFactorRequired) {
      document.getElementById('login-2fa').classList.remove('hidden');
      statusEl.textContent = out.message;
      return;
    }
    if (out.success) {
      statusEl.textContent = '✓ Verbunden';
      modalOverlay.classList.add('hidden');
      await pollPortalPulse();
      if (pendingOpenFunkiraAfterLogin) {
        pendingOpenFunkiraAfterLogin = false;
        openFunkiraStage();
      }
    } else {
      statusEl.textContent = out.message || 'Fehler';
    }
  });

  document.getElementById('btn-logout-token').addEventListener('click', async () => {
    await fetch('/api/auth/logout', { method: 'POST' });
    modalOverlay.classList.add('hidden');
    pollPortalPulse();
  });

  // =========================================================================
  // 7. SYSTEM TRAY POPOVERS (AUDIO 0..100, BLUETOOTH TEUFEL, WLAN 5 GHz)
  // =========================================================================
  const trayPopover = document.getElementById('tray-popover');
  const startMenu = document.getElementById('start-menu-popover');

  function hidePopovers() {
    trayPopover.classList.add('hidden');
    startMenu.classList.add('hidden');
  }

  document.addEventListener('pointerdown', (e) => {
    if (!e.target.closest('#tray-popover') &&
        !e.target.closest('#start-menu-popover') &&
        !e.target.closest('.tray-btn') &&
        !e.target.closest('.start-btn') &&
        !e.target.closest('#btn-gewerbe-bt') &&
        !e.target.closest('#btn-gewerbe-wifi') &&
        !e.target.closest('#btn-gewerbe-sound')) {
      hidePopovers();
    }
  });

  async function openAudioPopover() {
    trayPopover.classList.remove('hidden');
    trayPopover.innerHTML = '<div>Lade Audio...</div>';
    await pollSystemTray();
    const a = cachedTray?.audio || { volume: 65, isMuted: false, sinks: [] };
    trayPopover.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <strong style="color:var(--gold-bright);">Lautstärke</strong>
        <span id="pop-vol-num" style="font-weight:700;">${a.isMuted ? 'Stumm' : a.volume + '%'}</span>
      </div>
      <input type="range" min="0" max="100" step="1" value="${a.volume}" id="pop-vol-slider" />
      <button class="pill-btn" id="pop-mute-btn">${a.isMuted ? '🔇 Ton aktivieren' : '🔊 Stummschalten'}</button>
      <div style="margin-top:4px;font-size:12px;font-weight:700;color:var(--gold);">Ausgabegerät</div>
      <div style="display:flex;flex-direction:column;gap:6px;">
        ${(a.sinks || []).map(s => `
          <div class="list-row" data-sink="${esc(s.id)}" style="cursor:pointer;">
            <span style="font-size:12.5px;">${esc(s.name)}</span>
            <span class="${s.isDefault ? 'badge-connected' : 'badge-muted'}">${s.isDefault ? '✓ Aktiv' : 'Wählen'}</span>
          </div>
        `).join('') || '<div class="badge-muted">Standard-Ausgabe</div>'}
      </div>
    `;
    document.getElementById('pop-vol-slider').addEventListener('input', async (ev) => {
      const v = parseInt(ev.target.value, 10);
      document.getElementById('pop-vol-num').textContent = `${v}%`;
      await fetch('/api/audio/volume', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ volume: v })
      });
      pollSystemTray();
    });
    document.getElementById('pop-mute-btn').addEventListener('click', async () => {
      await fetch('/api/audio/mute', { method: 'POST' });
      openAudioPopover();
    });
    trayPopover.querySelectorAll('[data-sink]').forEach(el => {
      el.addEventListener('click', async () => {
        await fetch('/api/audio/sink', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ sinkId: el.getAttribute('data-sink') })
        });
        openAudioPopover();
      });
    });
  }

  async function openBluetoothPopover(scan = false) {
    trayPopover.classList.remove('hidden');
    trayPopover.innerHTML = `<div style="color:var(--gold-bright);font-weight:700;">Bluetooth ${scan ? '(Suche läuft...)' : ''}</div>`;
    const res = await fetch(`/api/bluetooth/list?scan=${scan ? '1' : '0'}`);
    const list = await res.json();
    trayPopover.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <strong style="color:var(--gold-bright);">Bluetooth</strong>
        <button class="pill-btn" id="bt-rescan-btn" style="padding:4px 10px;font-size:12px;">↻ Suchen</button>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;">
        ${list.map(d => `
          <div class="list-row">
            <div>
              <div style="font-size:13px;font-weight:600;">${esc(d.name)}</div>
              <div class="${d.isConnected ? 'badge-connected' : (d.isPaired ? 'badge-paired' : 'badge-muted')}">${esc(d.statusLabel)}</div>
            </div>
            <button class="pill-btn" data-bt-act="${d.isConnected ? 'disc' : 'conn'}" data-bt-mac="${esc(d.mac)}" style="padding:5px 10px;font-size:12px;">
              ${d.isConnected ? 'Trennen' : 'Verbinden'}
            </button>
          </div>
        `).join('') || '<div class="badge-muted">Auf „Suchen“ klicken</div>'}
      </div>
    `;
    document.getElementById('bt-rescan-btn').addEventListener('click', () => openBluetoothPopover(true));
    trayPopover.querySelectorAll('[data-bt-mac]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const mac = btn.getAttribute('data-bt-mac');
        const act = btn.getAttribute('data-bt-act');
        btn.textContent = '...';
        await fetch(act === 'disc' ? '/api/bluetooth/disconnect' : '/api/bluetooth/connect', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ mac })
        });
        await pollSystemTray();
        openBluetoothPopover(false);
      });
    });
  }

  async function openWifiPopover(rescan = false) {
    trayPopover.classList.remove('hidden');
    trayPopover.innerHTML = `<div style="color:var(--gold-bright);font-weight:700;">WLAN ${rescan ? '(Scanne...)' : ''}</div>`;
    const res = await fetch(`/api/wifi/list?rescan=${rescan ? '1' : '0'}`);
    const list = await res.json();
    trayPopover.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <strong style="color:var(--gold-bright);">WLAN</strong>
        <button class="pill-btn" id="wifi-rescan-btn" style="padding:4px 10px;font-size:12px;">↻ Scannen</button>
      </div>
      <div id="wifi-pw-box" class="hidden" style="display:flex;flex-direction:column;gap:6px;padding:8px;border:1px solid var(--gold-border);border-radius:8px;">
        <div id="wifi-selected-ssid" style="font-size:12.5px;font-weight:700;color:var(--gold-bright);"></div>
        <div class="pw-wrap">
          <input type="password" id="wifi-pw-inp" class="fw-input" placeholder="WLAN-Passwort" />
          <button type="button" class="pw-eye-btn" data-toggle-pw="wifi-pw-inp">👁</button>
        </div>
        <button class="pill-btn gold-primary" id="wifi-do-connect-btn">Verbinden</button>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;">
        ${list.map(w => `
          <div class="list-row" data-wifi-ssid="${esc(w.ssid)}" style="cursor:pointer;">
            <div>
              <div style="font-size:13px;font-weight:600;">${esc(w.ssid)}</div>
              <div class="${w.isConnected ? 'badge-connected' : 'badge-muted'}">${w.isConnected ? '✓ Verbunden · ' : ''}${esc(w.band)} · ${w.signal}%</div>
            </div>
            <span class="badge-paired">${w.isConnected ? 'Aktiv' : 'Wählen'}</span>
          </div>
        `).join('') || '<div class="badge-muted">Keine Netzwerke gefunden</div>'}
      </div>
    `;
    document.getElementById('wifi-rescan-btn').addEventListener('click', () => openWifiPopover(true));
    let targetSsid = '';
    trayPopover.querySelectorAll('[data-wifi-ssid]').forEach(row => {
      row.addEventListener('click', () => {
        targetSsid = row.getAttribute('data-wifi-ssid');
        document.getElementById('wifi-selected-ssid').textContent = targetSsid;
        document.getElementById('wifi-pw-box').classList.remove('hidden');
        document.getElementById('wifi-pw-inp').focus();
      });
    });
    document.getElementById('wifi-do-connect-btn')?.addEventListener('click', async (ev) => {
      ev.target.textContent = 'Verbinde...';
      const pw = document.getElementById('wifi-pw-inp').value;
      await fetch('/api/wifi/connect', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ssid: targetSsid, password: pw })
      });
      await pollSystemTray();
      openWifiPopover(false);
    });
  }

  document.getElementById('btn-gewerbe-sound').addEventListener('click', openAudioPopover);
  document.getElementById('tray-vol-btn').addEventListener('click', openAudioPopover);
  document.getElementById('btn-gewerbe-bt').addEventListener('click', () => openBluetoothPopover(false));
  document.getElementById('tray-bt-btn').addEventListener('click', () => openBluetoothPopover(false));
  document.getElementById('btn-gewerbe-wifi').addEventListener('click', () => openWifiPopover(false));
  document.getElementById('tray-wifi-btn').addEventListener('click', () => openWifiPopover(false));

  // =========================================================================
  // 8. DESKTOP APPS & WINDOW MANAGER
  // =========================================================================
  const APPS = [
    { id: 'gewerbe', title: 'Gewerbe-Monitor', icon: '💎', action: () => switchWorkspace('gewerbe') },
    { id: 'funkira', title: 'Funkira', icon: '✦', action: () => openFunkiraStage() },
    { id: 'files', title: 'Dateien', icon: '📁', w: 820, h: 520, render: renderFilesApp },
    { id: 'browser', title: 'Browser', icon: '🌐', action: () => fetch('/api/browser/open', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ url: 'https://mein-seelenfunke.de' }) }) },
    { id: 'music', title: 'Musikplayer', icon: '🎵', w: 460, h: 360, render: renderMusicApp },
    { id: 'tresor', title: 'Tresor', icon: '🔐', w: 680, h: 460, render: renderTresorApp },
    { id: 'calc', title: 'Taschenrechner', icon: '🧮', w: 360, h: 430, render: renderCalcApp },
    { id: 'notes', title: 'Kurznotizen', icon: '📝', w: 540, h: 420, render: renderNotesApp },
    { id: 'term', title: 'Terminal', icon: '⌨', w: 660, h: 420, render: renderTerminalApp },
    { id: 'settings', title: 'Einstellungen', icon: '⚙', w: 580, h: 500, render: renderSettingsApp }
  ];

  const desktopIconsEl = document.getElementById('desktop-icons');
  APPS.forEach(app => {
    const el = document.createElement('div');
    el.className = 'desktop-icon';
    el.innerHTML = `<div class="desktop-icon-glyph">${app.icon}</div><div class="desktop-icon-label">${esc(app.title)}</div>`;
    el.addEventListener('dblclick', () => launchApp(app.id));
    el.addEventListener('click', (e) => { if (e.detail === 1 && window.innerWidth < 900) launchApp(app.id); });
    desktopIconsEl.appendChild(el);
  });

  document.getElementById('btn-start-menu').addEventListener('click', () => {
    startMenu.classList.toggle('hidden');
    renderStartList('');
    if (!startMenu.classList.contains('hidden')) document.getElementById('start-search-input').focus();
  });

  document.getElementById('start-search-input').addEventListener('input', (e) => renderStartList(e.target.value));

  function renderStartList(q) {
    const norm = (q || '').trim().toLowerCase();
    const listEl = document.getElementById('start-apps-list');
    listEl.innerHTML = '';
    APPS.filter(a => !norm || a.title.toLowerCase().includes(norm)).forEach(app => {
      const row = document.createElement('div');
      row.className = 'list-row';
      row.style.cursor = 'pointer';
      row.innerHTML = `<span>${app.icon} &nbsp;<strong>${esc(app.title)}</strong></span><span class="badge-paired">Starten</span>`;
      row.addEventListener('click', () => { hidePopovers(); launchApp(app.id); });
      listEl.appendChild(row);
    });
  }

  document.getElementById('btn-os-reboot').addEventListener('click', () => {
    if (confirm('Mini-PC jetzt neu starten?')) {
      fetch('/api/power/action', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'reboot' }) });
    }
  });
  document.getElementById('btn-os-shutdown').addEventListener('click', () => {
    if (confirm('Mini-PC jetzt herunterfahren?')) {
      fetch('/api/power/action', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'poweroff' }) });
    }
  });

  let topZ = 20;
  const openWindows = new Map();

  function launchApp(appId, arg) {
    const app = APPS.find(a => a.id === appId);
    if (!app) return;
    if (app.action) { app.action(); return; }

    if (openWindows.has(appId)) {
      const existing = openWindows.get(appId);
      existing.style.zIndex = ++topZ;
      if (arg && existing._reloadWithArg) existing._reloadWithArg(arg);
      return;
    }

    const win = document.createElement('div');
    win.className = 'os-window';
    const w = Math.min(app.w || 640, window.innerWidth - 40);
    const h = Math.min(app.h || 440, window.innerHeight - 90);
    win.style.width = `${w}px`;
    win.style.height = `${h}px`;
    win.style.left = `${Math.max(20, (window.innerWidth - w) * 0.5 + (openWindows.size * 22))}px`;
    win.style.top = `${Math.max(20, (window.innerHeight - 60 - h) * 0.45 + (openWindows.size * 22))}px`;
    win.style.zIndex = ++topZ;

    win.innerHTML = `
      <div class="win-titlebar">
        <div class="win-title"><span>${app.icon}</span><span>${esc(app.title)}</span></div>
        <div class="win-controls">
          <button class="win-ctrl-btn max-btn" title="Maximieren">□</button>
          <button class="win-ctrl-btn close" title="Schließen">✕</button>
        </div>
      </div>
      <div class="win-body"></div>
    `;

    win.addEventListener('pointerdown', () => { win.style.zIndex = ++topZ; });
    win.querySelector('.max-btn').addEventListener('click', () => win.classList.toggle('maximized'));
    win.querySelector('.close').addEventListener('click', () => {
      win.remove();
      openWindows.delete(appId);
      syncTaskbar();
    });

    const tb = win.querySelector('.win-titlebar');
    tb.addEventListener('pointerdown', (ev) => {
      if (ev.target.closest('button')) return;
      if (win.classList.contains('maximized')) win.classList.remove('maximized');
      const startX = ev.clientX - win.offsetLeft;
      const startY = ev.clientY - win.offsetTop;
      function move(me) {
        win.style.left = `${Math.max(0, Math.min(window.innerWidth - 120, me.clientX - startX))}px`;
        win.style.top = `${Math.max(0, Math.min(window.innerHeight - 94, me.clientY - startY))}px`;
      }
      function up() {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
      }
      window.addEventListener('pointermove', move);
      window.addEventListener('pointerup', up);
    });

    document.getElementById('windows-layer').appendChild(win);
    openWindows.set(appId, win);
    syncTaskbar();
    app.render(win.querySelector('.win-body'), win, arg);
  }

  function syncTaskbar() {
    const bar = document.getElementById('taskbar-windows');
    bar.innerHTML = '';
    for (const [id, win] of openWindows.entries()) {
      const app = APPS.find(a => a.id === id);
      const b = document.createElement('button');
      b.className = 'task-app-btn active';
      b.innerHTML = `<span>${app.icon}</span><span>${esc(app.title)}</span>`;
      b.addEventListener('click', () => { win.style.zIndex = ++topZ; });
      bar.appendChild(b);
    }
  }

  // =========================================================================
  // 9. APP IMPLEMENTATIONS (FILES, MUSIC, TRESOR, CALC, NOTES, TERM, SETTINGS)
  // =========================================================================
  let lbImages = [];
  let lbIdx = 0;
  function openLightbox(images, startIdx) {
    lbImages = images;
    lbIdx = startIdx;
    showLbSlide();
    document.getElementById('lightbox-overlay').classList.remove('hidden');
  }
  function showLbSlide() {
    if (!lbImages.length) return;
    const item = lbImages[lbIdx];
    const url = `/api/files/view?path=${encodeURIComponent(item.fullPath)}`;
    const isPdf = String(item.fullPath || item.name || '').toLowerCase().endsWith('.pdf');
    const imgEl = document.getElementById('lightbox-img');
    const pdfEl = document.getElementById('lightbox-pdf');
    if (isPdf && pdfEl) {
      imgEl.classList.add('hidden');
      imgEl.src = '';
      pdfEl.classList.remove('hidden');
      pdfEl.src = url;
    } else {
      if (pdfEl) { pdfEl.classList.add('hidden'); pdfEl.src = 'about:blank'; }
      imgEl.classList.remove('hidden');
      imgEl.src = url;
    }
    document.getElementById('lb-caption').textContent = `${item.name} (${lbIdx + 1}/${lbImages.length})`;
  }
  document.getElementById('lb-prev').addEventListener('click', () => { lbIdx = (lbIdx - 1 + lbImages.length) % lbImages.length; showLbSlide(); });
  document.getElementById('lb-next').addEventListener('click', () => { lbIdx = (lbIdx + 1) % lbImages.length; showLbSlide(); });
  document.getElementById('lb-close').addEventListener('click', () => {
    const pdfEl = document.getElementById('lightbox-pdf');
    if (pdfEl) pdfEl.src = 'about:blank';
    document.getElementById('lightbox-overlay').classList.add('hidden');
  });

  async function renderFilesApp(body) {
    body.innerHTML = `
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="pill-btn" id="fm-up-btn">↑ Hoch</button>
        <input type="text" class="fw-input" id="fm-path-inp" readonly style="flex:1;" />
        <input type="text" class="fw-input" id="fm-fts-inp" placeholder="🔎 Volltext-Suche (alle Unterlagen)..." style="max-width:250px;" />
        <button class="pill-btn" id="fm-mkdir-btn">+ Ordner</button>
      </div>
      <div class="fm-layout" style="flex:1;">
        <div class="fm-sidebar" id="fm-roots"></div>
        <div style="overflow:auto;">
          <table class="fm-table">
            <thead><tr><th>Name</th><th>Geändert / Bereich</th><th>Größe</th><th>Aktion</th></tr></thead>
            <tbody id="fm-rows"></tbody>
          </table>
        </div>
      </div>
    `;

    let curPath = '';
    let parentPath = '';
    let searchDebounce = null;

    const rootsRes = await fetch('/api/files/roots');
    const roots = await rootsRes.json();
    const rootsEl = body.querySelector('#fm-roots');
    roots.forEach(r => {
      const b = document.createElement('button');
      b.className = 'fm-nav-btn';
      b.textContent = r.label;
      b.addEventListener('click', () => {
        body.querySelector('#fm-fts-inp').value = '';
        loadDir(r.path);
      });
      rootsEl.appendChild(b);
    });

    function openEntryPreview(fullPath, name, ext, siblingPreviews) {
      const lowerExt = (ext || '').toLowerCase();
      if (['.png','.jpg','.jpeg','.webp','.gif','.svg','.pdf'].includes(lowerExt)) {
        const list = siblingPreviews && siblingPreviews.length
          ? siblingPreviews
          : [{ fullPath, name }];
        const idx = Math.max(0, list.findIndex(x => x.fullPath === fullPath));
        openLightbox(list, idx);
      } else if (['.mp3','.wav','.flac','.ogg'].includes(lowerExt)) {
        launchApp('music', fullPath);
      } else if (lowerExt === '.kdbx') {
        launchApp('tresor', fullPath);
      } else {
        fetch('/api/browser/open', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ url: `http://127.0.0.1:17700/api/files/view?path=${encodeURIComponent(fullPath)}` })
        });
      }
    }

    async function runArchiveSearch(q) {
      const res = await fetch(`/api/archive/search?q=${encodeURIComponent(q)}`);
      const data = await res.json();
      body.querySelector('#fm-path-inp').value = `Volltext-Suche (${data.hits?.length ?? 0} Treffer in ${data.elapsedMs ?? 0} ms · ${data.totalIndexed ?? 0} indexiert)`;
      const rowsEl = body.querySelector('#fm-rows');
      rowsEl.innerHTML = '';
      const hits = data.hits || [];
      hits.forEach(h => {
        const ext = '.' + (h.fileName.split('.').pop() || '').toLowerCase();
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.innerHTML = `
          <td>📄 &nbsp;<strong>${esc(h.fileName)}</strong></td>
          <td class="badge-muted">${esc(h.docDate || '')} · ${esc(h.pillar)}/${esc(h.subfolder || '')}</td>
          <td class="badge-muted">${Math.max(1, Math.round((h.sizeBytes || 0) / 1024))} KB</td>
          <td><button class="pill-btn" style="padding:3px 8px;font-size:11px;">Öffnen</button></td>
        `;
        const openFn = () => openEntryPreview(h.fullPath, h.fileName, ext, [{ fullPath: h.fullPath, name: h.fileName }]);
        tr.addEventListener('dblclick', openFn);
        tr.querySelector('button').addEventListener('click', openFn);
        rowsEl.appendChild(tr);
      });
    }

    async function loadDir(p) {
      const res = await fetch(`/api/files/list?path=${encodeURIComponent(p || '')}`);
      const data = await res.json();
      curPath = data.currentPath;
      parentPath = data.parentPath;
      body.querySelector('#fm-path-inp').value = curPath;

      const rowsEl = body.querySelector('#fm-rows');
      rowsEl.innerHTML = '';
      const lbCandidates = data.entries.filter(e => ['.png','.jpg','.jpeg','.webp','.gif','.svg','.pdf'].includes(e.extension));

      data.entries.forEach(e => {
        const tr = document.createElement('tr');
        const icon = e.isDirectory ? '📁' : (['.png','.jpg','.jpeg','.webp','.gif','.svg'].includes(e.extension) ? '🖼' : (['.mp3','.wav','.flac','.ogg'].includes(e.extension) ? '🎵' : '📄'));
        tr.innerHTML = `
          <td>${icon} &nbsp;${esc(e.name)}</td>
          <td class="badge-muted">${esc(e.modifiedAt)}</td>
          <td class="badge-muted">${e.isDirectory ? 'Ordner' : esc(e.sizeFormatted)}</td>
          <td>
            ${data.isTrash
              ? `<button class="pill-btn" data-restore="${esc(e.fullPath)}" style="padding:3px 8px;font-size:11px;">↩ Wiederherstellen</button>`
              : `<button class="pill-btn" data-del="${esc(e.fullPath)}" style="padding:3px 8px;font-size:11px;">🗑</button>`}
          </td>
        `;
        tr.addEventListener('dblclick', (ev) => {
          if (ev.target.closest('button')) return;
          if (e.isDirectory) { loadDir(e.fullPath); return; }
          openEntryPreview(e.fullPath, e.name, e.extension, lbCandidates);
        });
        rowsEl.appendChild(tr);
      });

      rowsEl.querySelectorAll('[data-del]').forEach(btn => {
        btn.addEventListener('click', async (ev) => {
          ev.stopPropagation();
          await fetch('/api/files/trash', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ path: btn.getAttribute('data-del') })
          });
          loadDir(curPath);
        });
      });

      rowsEl.querySelectorAll('[data-restore]').forEach(btn => {
        btn.addEventListener('click', async (ev) => {
          ev.stopPropagation();
          await fetch('/api/files/restore', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ path: btn.getAttribute('data-restore') })
          });
          loadDir(curPath);
        });
      });
    }

    body.querySelector('#fm-up-btn').addEventListener('click', () => {
      body.querySelector('#fm-fts-inp').value = '';
      loadDir(parentPath);
    });
    body.querySelector('#fm-fts-inp').addEventListener('input', (ev) => {
      clearTimeout(searchDebounce);
      const q = ev.target.value.trim();
      searchDebounce = setTimeout(() => {
        if (!q) loadDir(curPath);
        else runArchiveSearch(q);
      }, 140);
    });
    body.querySelector('#fm-mkdir-btn').addEventListener('click', async () => {
      const name = prompt('Neuer Ordnername:');
      if (!name) return;
      await fetch('/api/files/mkdir', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ parent: curPath, name })
      });
      loadDir(curPath);
    });

    loadDir('');
  }

  function renderMusicApp(body, win, initialTrackPath) {
    body.innerHTML = `
      <div style="font-weight:700;color:var(--gold-bright);" id="mp-track-title">Kein Titel gewählt</div>
      <audio id="mp-audio" controls style="width:100%;"></audio>
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:12.5px;">Lautstärke: <strong id="mp-vol-lbl">80%</strong></span>
        <input type="range" min="0" max="100" step="1" value="80" id="mp-vol-range" />
      </div>
      <input type="text" class="fw-input" id="mp-stream-url" placeholder="Audio-Pfad oder Stream-URL eingeben..." />
      <button class="pill-btn gold-primary" id="mp-load-btn">▶ Laden (Start per Klick)</button>
    `;
    const audio = body.querySelector('#mp-audio');
    audio.volume = 0.8;

    body.querySelector('#mp-vol-range').addEventListener('input', (e) => {
      const v = parseInt(e.target.value, 10);
      body.querySelector('#mp-vol-lbl').textContent = `${v}%`;
      audio.volume = v / 100;
    });

    function loadSource(src, label) {
      body.querySelector('#mp-track-title').textContent = label || src;
      audio.src = src.startsWith('/') ? `/api/files/view?path=${encodeURIComponent(src)}` : src;
    }

    body.querySelector('#mp-load-btn').addEventListener('click', () => {
      const val = body.querySelector('#mp-stream-url').value.trim();
      if (val) loadSource(val, val.split('/').pop());
    });

    win._reloadWithArg = (p) => loadSource(p, p.split('/').pop());
    if (initialTrackPath) loadSource(initialTrackPath, initialTrackPath.split('/').pop());
  }

  function renderTresorApp(body, win, initialKdbxPath) {
    body.innerHTML = `
      <div id="tresor-unlock-box" style="display:flex;flex-direction:column;gap:10px;">
        <input type="text" class="fw-input" id="tr-path" placeholder="Pfad zur .kdbx Datei" value="${esc(initialKdbxPath || '/home/alina/Meine_Daten/Tresor.kdbx')}" />
        <div class="pw-wrap">
          <input type="password" class="fw-input" id="tr-pw" placeholder="Master-Passwort" />
          <button type="button" class="pw-eye-btn" data-toggle-pw="tr-pw">👁</button>
        </div>
        <input type="text" class="fw-input" id="tr-keyfile" placeholder="Optional: Pfad zur Schlüsseldatei (.keyx / .key)" />
        <div id="tr-err" style="color:var(--danger);font-size:12.5px;"></div>
        <button class="pill-btn gold-primary" id="tr-open-btn">🔓 Tresor entsperren</button>
      </div>
      <div id="tresor-entries-box" class="hidden" style="display:flex;flex-direction:column;gap:10px;height:100%;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <strong id="tr-db-badge" style="color:var(--gold-bright);font-size:13px;"></strong>
          <button class="pill-btn" id="tr-lock-btn">🔒 Sperren</button>
        </div>
        <input type="text" class="fw-input" id="tr-search" placeholder="Einträge durchsuchen..." />
        <div style="overflow:auto;flex:1;">
          <table class="fm-table">
            <thead><tr><th>Gruppe</th><th>Titel</th><th>Benutzer</th><th>Aktion</th></tr></thead>
            <tbody id="tr-rows"></tbody>
          </table>
        </div>
      </div>
    `;

    let allEntries = [];

    function renderRows(q) {
      const norm = (q || '').toLowerCase();
      const rows = body.querySelector('#tr-rows');
      rows.innerHTML = '';
      allEntries.filter(e => !norm || e.title.toLowerCase().includes(norm) || e.userName.toLowerCase().includes(norm)).forEach(e => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="badge-muted">${esc(e.group)}</td>
          <td><strong>${esc(e.title)}</strong></td>
          <td>${esc(e.userName)}</td>
          <td><button class="pill-btn" style="padding:3px 9px;font-size:11.5px;">Passwort kopieren (12s)</button></td>
        `;
        tr.querySelector('button').addEventListener('click', async (ev) => {
          await copyWithFallback(e.password);
          ev.target.textContent = '✓ Kopiert (12s)';
          setTimeout(() => { copyWithFallback(''); ev.target.textContent = 'Passwort kopieren (12s)'; }, 12000);
        });
        rows.appendChild(tr);
      });
    }

    body.querySelector('#tr-open-btn').addEventListener('click', async () => {
      const res = await fetch('/api/tresor/open', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          filePath: body.querySelector('#tr-path').value,
          password: body.querySelector('#tr-pw').value,
          keyfilePath: body.querySelector('#tr-keyfile').value
        })
      });
      const data = await res.json();
      if (!data.ok) {
        body.querySelector('#tr-err').textContent = data.message || 'Fehler beim Öffnen';
        return;
      }
      body.querySelector('#tr-pw').value = '';
      allEntries = data.entries || [];
      body.querySelector('#tr-db-badge').textContent = `${data.name} · ${data.badge}`;
      body.querySelector('#tresor-unlock-box').classList.add('hidden');
      body.querySelector('#tresor-entries-box').classList.remove('hidden');
      renderRows('');
    });

    body.querySelector('#tr-search').addEventListener('input', (e) => renderRows(e.target.value));
    body.querySelector('#tr-lock-btn').addEventListener('click', async () => {
      allEntries = [];
      await fetch('/api/tresor/lock', { method: 'POST' });
      body.querySelector('#tresor-entries-box').classList.add('hidden');
      body.querySelector('#tresor-unlock-box').classList.remove('hidden');
    });
  }

  function renderCalcApp(body) {
    body.innerHTML = `
      <input type="text" class="fw-input" id="calc-disp" value="0" readonly style="font-size:24px;text-align:right;font-weight:800;" />
      <div style="display:flex;gap:8px;">
        <button class="pill-btn" style="flex:1;justify-content:center;" id="calc-vat-add">+19% MwSt</button>
        <button class="pill-btn" style="flex:1;justify-content:center;" id="calc-vat-sub">Netto (-19%)</button>
      </div>
      <div class="calc-grid" id="calc-keys"></div>
    `;
    const disp = body.querySelector('#calc-disp');
    let expr = '';
    const keys = ['7','8','9','/','4','5','6','*','1','2','3','-','C','0','.','+','='];
    const grid = body.querySelector('#calc-keys');
    keys.forEach(k => {
      const b = document.createElement('button');
      b.className = 'calc-btn' + (['=','+','-','*','/'].includes(k) ? ' gold' : '');
      if (k === '=') b.style.gridColumn = 'span 4';
      b.textContent = k;
      b.addEventListener('click', () => {
        if (k === 'C') { expr = ''; disp.value = '0'; return; }
        if (k === '=') {
          try {
            const safe = expr.replace(/[^0-9+\-*/.()]/g, '');
            const val = Function(`"use strict"; return (${safe || 0});`)();
            expr = Number(val.toFixed(6)).toString();
            disp.value = expr;
          } catch { disp.value = 'Fehler'; expr = ''; }
          return;
        }
        expr += k;
        disp.value = expr;
      });
      grid.appendChild(b);
    });
    body.querySelector('#calc-vat-add').addEventListener('click', () => {
      const n = parseFloat(disp.value) || 0;
      expr = (Math.round(n * 1.19 * 100) / 100).toFixed(2);
      disp.value = expr;
    });
    body.querySelector('#calc-vat-sub').addEventListener('click', () => {
      const n = parseFloat(disp.value) || 0;
      expr = (Math.round((n / 1.19) * 100) / 100).toFixed(2);
      disp.value = expr;
    });
  }

  async function renderNotesApp(body) {
    body.innerHTML = `
      <div style="display:flex;gap:8px;">
        <input type="text" class="fw-input" id="nt-title" placeholder="Titel" />
        <button class="pill-btn gold-primary" id="nt-save">Speichern</button>
      </div>
      <textarea class="fw-input" id="nt-content" style="height:120px;resize:none;" placeholder="Notiz eingeben..."></textarea>
      <div id="nt-list" style="display:flex;flex-direction:column;gap:6px;overflow:auto;flex:1;"></div>
    `;
    let curId = '';
    async function reload() {
      const res = await fetch('/api/notes');
      const notes = await res.json();
      const list = body.querySelector('#nt-list');
      list.innerHTML = '';
      notes.forEach(n => {
        const r = document.createElement('div');
        r.className = 'list-row';
        r.style.cursor = 'pointer';
        r.innerHTML = `<div><strong>${esc(n.title)}</strong> <span class="badge-muted">${esc(n.updatedAt)}</span></div><button class="pill-btn" style="padding:3px 8px;">🗑</button>`;
        r.addEventListener('click', (ev) => {
          if (ev.target.closest('button')) return;
          curId = n.id;
          body.querySelector('#nt-title').value = n.title;
          body.querySelector('#nt-content').value = n.content;
        });
        r.querySelector('button').addEventListener('click', async () => {
          await fetch('/api/notes/delete', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ id: n.id }) });
          reload();
        });
        list.appendChild(r);
      });
    }
    body.querySelector('#nt-save').addEventListener('click', async () => {
      await fetch('/api/notes', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          id: curId,
          title: body.querySelector('#nt-title').value || 'Notiz',
          content: body.querySelector('#nt-content').value
        })
      });
      curId = '';
      body.querySelector('#nt-title').value = '';
      body.querySelector('#nt-content').value = '';
      reload();
    });
    reload();
  }

  function renderTerminalApp(body) {
    body.innerHTML = `
      <pre id="term-out" style="flex:1;background:#040406;border:1px solid var(--gold-border);border-radius:8px;padding:10px;overflow:auto;font-family:monospace;font-size:12.5px;color:#E5E7EB;">Funkenwerk OS Linux Terminal bereit.\n</pre>
      <div style="display:flex;gap:8px;">
        <input type="text" class="fw-input" id="term-inp" placeholder="Befehl eingeben..." style="font-family:monospace;" />
        <button class="pill-btn gold-primary" id="term-run">Ausführen</button>
      </div>
    `;
    const out = body.querySelector('#term-out');
    const inp = body.querySelector('#term-inp');
    async function runCmd() {
      const c = inp.value.trim();
      if (!c) return;
      inp.value = '';
      out.textContent += `\n$ ${c}\n`;
      const res = await fetch('/api/terminal/exec', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ command: c })
      });
      const data = await res.json();
      if (data.output) out.textContent += data.output + '\n';
      out.scrollTop = out.scrollHeight;
    }
    body.querySelector('#term-run').addEventListener('click', runCmd);
    inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') runCmd(); });
  }

  async function renderSettingsApp(body) {
    await pollSystemTray();
    let nas = {};
    try {
      const nasRes = await fetch('/api/nas-backup/status');
      nas = await nasRes.json();
    } catch { }
    const st = cachedTray?.stats || {};
    const cam = cachedTray?.camera || {};
    body.innerHTML = `
      <div class="list-row"><span>Gerätename</span><strong style="color:var(--gold-bright);">${esc(st.hostname || 'funkenwerk-minipc')}</strong></div>
      <div class="list-row"><span>Lokale IP-Adresse (SSH Port 22)</span><strong style="color:var(--gold-bright);">${esc(st.ipAddress || '-')}</strong></div>
      <div class="list-row"><span>CPU / Arbeitsspeicher</span><strong>${st.cpuPercent ?? 0}% · ${st.ramUsedMb ?? 0} / ${st.ramTotalMb ?? 0} MB</strong></div>
      <div class="list-row"><span>Funkira Auge & Ohr (USB)</span><span class="${cam.hasCamera ? 'badge-connected' : 'badge-muted'}">${esc(cam.hasCamera ? cam.cameraName : 'Keine Kamera')}</span></div>
      <div class="list-row"><span>Dokumenten-Index (SQLite FTS5)</span><strong style="color:var(--gold-bright);">${nas.indexedDocumentsCount ?? 0} Dateien</strong></div>
      <div class="list-row">
        <span>Hintergrund</span>
        <div style="display:flex;gap:6px;">
          <button class="pill-btn ${currentWallpaperMode !== 'Kosmos' ? 'gold-primary' : ''}" id="set-wp-video" style="padding:4px 10px;font-size:12px;">🎬 Atelier-Video</button>
          <button class="pill-btn ${currentWallpaperMode === 'Kosmos' ? 'gold-primary' : ''}" id="set-wp-kosmos" style="padding:4px 10px;font-size:12px;">🌌 Kosmos</button>
        </div>
      </div>
      <div style="padding:10px;border:1px solid var(--gold-border);border-radius:10px;display:flex;flex-direction:column;gap:8px;margin-top:4px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <strong style="color:var(--gold-bright);font-size:13px;">NAS Delta-Backup (//${esc(nas.nasHost || '192.168.188.33')}/${esc(nas.nasShare || 'Backups')})</strong>
          <span class="${nas.configured ? 'badge-connected' : 'badge-muted'}" id="set-nas-badge">${nas.configured ? '✓ Aktiv' : 'Passwort fehlt'}</span>
        </div>
        <div style="display:flex;gap:6px;">
          <input type="text" class="fw-input" id="set-nas-user" value="${esc(nas.nasUsername || 'alina')}" placeholder="NAS-Benutzer" style="max-width:130px;" />
          <div class="pw-wrap" style="flex:1;">
            <input type="password" class="fw-input" id="set-nas-pw" placeholder="${nas.configured ? 'NAS-Passwort gespeichert (Vault)' : 'NAS-Passwort eingeben'}" />
            <button type="button" class="pw-eye-btn" data-toggle-pw="set-nas-pw">👁</button>
          </div>
          <button class="pill-btn" id="set-nas-save">Speichern</button>
          <button class="pill-btn gold-primary" id="set-nas-run">Jetzt sichern</button>
        </div>
        <div class="badge-muted" id="set-nas-result">${esc(nas.lastBackupResult || 'Bereit')} (${esc(nas.lastBackupAt || '-')})</div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
        <button class="pill-btn gold-primary" id="set-login-btn">🔗 Portal-Konto</button>
        <button class="pill-btn funkira-btn" id="set-funkira-btn">✦ Funkira öffnen</button>
        <button class="pill-btn" id="set-wifi-btn">📶 WLAN</button>
        <button class="pill-btn" id="set-bt-btn">ᛒ Bluetooth</button>
        <button class="pill-btn" id="set-sleep-btn">☾ Nachtruhe (08:00)</button>
      </div>
    `;
    body.querySelector('#set-wp-video').addEventListener('click', async () => {
      await setWallpaperMode('AtelierVideo');
      renderSettingsApp(body);
    });
    body.querySelector('#set-wp-kosmos').addEventListener('click', async () => {
      await setWallpaperMode('Kosmos');
      renderSettingsApp(body);
    });
    body.querySelector('#set-nas-save').addEventListener('click', async () => {
      const res = await fetch('/api/nas-backup/configure', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          nasUsername: body.querySelector('#set-nas-user').value,
          nasPassword: body.querySelector('#set-nas-pw').value
        })
      });
      await res.json();
      renderSettingsApp(body);
    });
    body.querySelector('#set-nas-run').addEventListener('click', async (ev) => {
      ev.currentTarget.textContent = 'Sichere...';
      const pwVal = body.querySelector('#set-nas-pw').value;
      if (pwVal) {
        await fetch('/api/nas-backup/configure', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            nasUsername: body.querySelector('#set-nas-user').value,
            nasPassword: pwVal
          })
        });
      }
      const res = await fetch('/api/nas-backup/run-now', { method: 'POST' });
      const out = await res.json();
      body.querySelector('#set-nas-result').textContent = out.summary || 'Fertig';
      ev.currentTarget.textContent = 'Jetzt sichern';
    });
    body.querySelector('#set-login-btn').addEventListener('click', () => modalOverlay.classList.remove('hidden'));
    body.querySelector('#set-funkira-btn').addEventListener('click', () => openFunkiraStage());
    body.querySelector('#set-wifi-btn').addEventListener('click', () => openWifiPopover(true));
    body.querySelector('#set-bt-btn').addEventListener('click', () => openBluetoothPopover(true));
    body.querySelector('#set-sleep-btn').addEventListener('click', () => document.getElementById('btn-night-sleep').click());
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  async function copyWithFallback(text) {
    if (navigator.clipboard && window.isSecureContext) {
      try { await navigator.clipboard.writeText(text); return; } catch { }
    }
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch { }
    ta.remove();
  }
})();

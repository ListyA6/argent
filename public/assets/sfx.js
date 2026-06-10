/* ============================================================
   ARGENT SFX — synthesized, satisfying, zero assets.
   Every category gets its own voice; saving money sounds
   like dropping a coin into a vault.
   ============================================================ */

(function () {
  let ctx = null;
  let master = null;
  let enabled = localStorage.getItem('argent.sound') !== 'off';

  function ac() {
    if (!ctx) {
      ctx = new (window.AudioContext || window.webkitAudioContext)();
      master = ctx.createGain();
      master.gain.value = 0.5;
      master.connect(ctx.destination);
    }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }

  function env(node, t0, attack, peak, decay) {
    node.gain.setValueAtTime(0.0001, t0);
    node.gain.exponentialRampToValueAtTime(peak, t0 + attack);
    node.gain.exponentialRampToValueAtTime(0.0001, t0 + attack + decay);
  }

  function tone(freq, type, t0, attack, peak, decay, detune = 0, dest = null) {
    const c = ac();
    const o = c.createOscillator();
    const g = c.createGain();
    o.type = type;
    o.frequency.setValueAtTime(freq, t0);
    o.detune.value = detune;
    env(g, t0, attack, peak, decay);
    o.connect(g).connect(dest || master);
    o.start(t0);
    o.stop(t0 + attack + decay + 0.05);
    return o;
  }

  function noise(t0, duration, peak, filterType, freqFrom, freqTo, q = 1) {
    const c = ac();
    const len = Math.ceil(c.sampleRate * duration);
    const buf = c.createBuffer(1, len, c.sampleRate);
    const data = buf.getChannelData(0);
    for (let i = 0; i < len; i++) data[i] = Math.random() * 2 - 1;
    const src = c.createBufferSource();
    src.buffer = buf;
    const f = c.createBiquadFilter();
    f.type = filterType;
    f.Q.value = q;
    f.frequency.setValueAtTime(freqFrom, t0);
    f.frequency.exponentialRampToValueAtTime(Math.max(40, freqTo), t0 + duration);
    const g = c.createGain();
    env(g, t0, 0.004, peak, duration);
    src.connect(f).connect(g).connect(master);
    src.start(t0);
    src.stop(t0 + duration + 0.05);
  }

  const presets = {
    // Food — soft bubble pop
    pop(t) {
      const c = ac();
      const o = c.createOscillator();
      const g = c.createGain();
      o.type = 'sine';
      o.frequency.setValueAtTime(560, t);
      o.frequency.exponentialRampToValueAtTime(180, t + 0.09);
      env(g, t, 0.004, 0.55, 0.11);
      o.connect(g).connect(master);
      o.start(t); o.stop(t + 0.17);
      noise(t, 0.03, 0.18, 'highpass', 2400, 3200, 0.7);
    },

    // Going Out — two-note sparkle chime
    chime(t) {
      tone(1318.5, 'sine', t, 0.005, 0.32, 0.34);          // E6
      tone(1318.5 * 2.01, 'sine', t, 0.005, 0.08, 0.22);
      tone(1760, 'sine', t + 0.09, 0.005, 0.3, 0.42);      // A6
      tone(1760 * 2.005, 'sine', t + 0.09, 0.005, 0.07, 0.3);
    },

    // Protein — deep satisfying thock
    thock(t) {
      const c = ac();
      const o = c.createOscillator();
      const g = c.createGain();
      o.type = 'sine';
      o.frequency.setValueAtTime(190, t);
      o.frequency.exponentialRampToValueAtTime(70, t + 0.07);
      env(g, t, 0.003, 0.85, 0.12);
      o.connect(g).connect(master);
      o.start(t); o.stop(t + 0.18);
      noise(t, 0.045, 0.3, 'bandpass', 900, 300, 1.4);
    },

    // Transport — whoosh sweep
    whoosh(t) {
      noise(t, 0.3, 0.4, 'bandpass', 350, 2600, 1.8);
    },

    // Bills — single clean bell
    ding(t) {
      tone(987.8, 'sine', t, 0.004, 0.4, 0.6);             // B5
      tone(987.8 * 2.76, 'sine', t, 0.004, 0.1, 0.4);
      tone(987.8 * 5.4, 'sine', t, 0.004, 0.04, 0.25);
    },

    // Fun — rising arcade arpeggio
    arcade(t) {
      tone(523.25, 'square', t, 0.004, 0.12, 0.09);        // C5
      tone(659.25, 'square', t + 0.07, 0.004, 0.12, 0.09); // E5
      tone(784, 'square', t + 0.14, 0.004, 0.13, 0.16);    // G5
      tone(1046.5, 'square', t + 0.21, 0.004, 0.13, 0.22); // C6
    },

    // Misc — tidy click
    click(t) {
      noise(t, 0.025, 0.32, 'bandpass', 2000, 1200, 2.5);
      tone(420, 'triangle', t, 0.002, 0.18, 0.05);
    },

    // Universal: coin drop into the vault (save success layer)
    coin(t) {
      tone(2093, 'triangle', t, 0.002, 0.26, 0.07);
      tone(2637, 'triangle', t + 0.06, 0.002, 0.26, 0.32);
      tone(2637 * 1.5, 'sine', t + 0.06, 0.002, 0.06, 0.2);
    },

    // Keypad tick
    tick(t) {
      noise(t, 0.018, 0.16, 'highpass', 3000, 4000, 0.8);
      tone(720, 'sine', t, 0.002, 0.07, 0.03);
    },

    // PIN unlock — vault opening
    unlock(t) {
      tone(392, 'sine', t, 0.005, 0.3, 0.12);
      tone(587.3, 'sine', t + 0.1, 0.005, 0.3, 0.14);
      tone(784, 'sine', t + 0.2, 0.005, 0.32, 0.4);
      tone(1568, 'sine', t + 0.2, 0.005, 0.08, 0.3);
    },

    // Error buzz
    error(t) {
      tone(160, 'sawtooth', t, 0.004, 0.22, 0.16);
      tone(151, 'sawtooth', t, 0.004, 0.22, 0.16);
    },

    // Delete — reverse pop
    trash(t) {
      const c = ac();
      const o = c.createOscillator();
      const g = c.createGain();
      o.type = 'sine';
      o.frequency.setValueAtTime(420, t);
      o.frequency.exponentialRampToValueAtTime(120, t + 0.16);
      env(g, t, 0.005, 0.4, 0.18);
      o.connect(g).connect(master);
      o.start(t); o.stop(t + 0.25);
    },
  };

  window.SFX = {
    play(name) {
      if (!enabled || !presets[name]) return;
      try {
        const t = ac().currentTime + 0.001;
        presets[name](t);
      } catch (e) { /* audio is never worth crashing over */ }
    },
    get enabled() { return enabled; },
    set enabled(v) {
      enabled = !!v;
      localStorage.setItem('argent.sound', enabled ? 'on' : 'off');
    },
    warm() { try { ac(); } catch (e) {} },
  };
})();

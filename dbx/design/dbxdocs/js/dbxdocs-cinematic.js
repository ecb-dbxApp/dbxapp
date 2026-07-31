(function (window, document) {
    "use strict";

    var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    function formatTime(seconds) {
        var value = Math.max(0, Math.round(seconds));
        var minutes = Math.floor(value / 60);
        var remainder = value % 60;
        return String(minutes).padStart(2, "0") + ":" + String(remainder).padStart(2, "0");
    }

    function createSoundscape() {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return null;

        var context = new AudioContext();
        var master = context.createGain();
        var compressor = context.createDynamicsCompressor();
        var filter = context.createBiquadFilter();
        var lfo = context.createOscillator();
        var lfoGain = context.createGain();
        var voices = [];
        var enabled = false;
        var pulseTimer = 0;
        var pulseStep = 0;
        var pulseNotes = [110, 164.81, 220, 329.63, 220, 164.81];

        master.gain.value = 0;
        filter.type = "lowpass";
        filter.frequency.value = 920;
        filter.Q.value = 0.9;
        lfo.type = "sine";
        lfo.frequency.value = 0.09;
        lfoGain.gain.value = 190;
        compressor.threshold.value = -20;
        compressor.knee.value = 18;
        compressor.ratio.value = 4;
        compressor.attack.value = 0.02;
        compressor.release.value = 0.3;
        lfo.connect(lfoGain);
        lfoGain.connect(filter.frequency);
        filter.connect(master);
        master.connect(compressor);
        compressor.connect(context.destination);

        [
            { frequency: 55, type: "sine", gain: 0.72 },
            { frequency: 82.41, type: "triangle", gain: 0.24 },
            { frequency: 164.81, type: "sine", gain: 0.08 }
        ].forEach(function (voice) {
            var oscillator = context.createOscillator();
            var gain = context.createGain();
            oscillator.type = voice.type;
            oscillator.frequency.value = voice.frequency;
            gain.gain.value = voice.gain;
            oscillator.connect(gain);
            gain.connect(filter);
            oscillator.start();
            voices.push(oscillator);
        });
        lfo.start();

        function accent(frequency, strength) {
            if (!enabled || context.state !== "running") return;
            var now = context.currentTime;
            var oscillator = context.createOscillator();
            var gain = context.createGain();
            oscillator.type = "sine";
            oscillator.frequency.setValueAtTime(frequency, now);
            oscillator.frequency.exponentialRampToValueAtTime(frequency * 1.5, now + 0.7);
            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(Math.max(0.12, strength || 0.2), now + 0.035);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 1.15);
            oscillator.connect(gain);
            gain.connect(filter);
            oscillator.start(now);
            oscillator.stop(now + 1.2);
        }

        function startPulse() {
            if (pulseTimer) return;
            accent(220, 0.24);
            pulseTimer = window.setInterval(function () {
                accent(pulseNotes[pulseStep % pulseNotes.length], 0.16);
                pulseStep++;
            }, 1850);
        }

        function stopPulse() {
            if (!pulseTimer) return;
            window.clearInterval(pulseTimer);
            pulseTimer = 0;
        }

        return {
            context: context,
            setEnabled: function (active) {
                enabled = active;
                if (active) {
                    context.resume().then(function () {
                        master.gain.cancelScheduledValues(context.currentTime);
                        master.gain.setTargetAtTime(0.065, context.currentTime, 0.08);
                        startPulse();
                    });
                } else {
                    stopPulse();
                    master.gain.cancelScheduledValues(context.currentTime);
                    master.gain.setTargetAtTime(0.0001, context.currentTime, 0.035);
                }
            },
            accent: accent,
            destroy: function () {
                enabled = false;
                stopPulse();
                voices.forEach(function (oscillator) {
                    try { oscillator.stop(); } catch (ignore) {}
                });
                try { lfo.stop(); } catch (ignore) {}
                context.close();
            }
        };
    }

    function initialize(root) {
        var duration = Math.max(1, Number(root.dataset.duration) || 84);
        var toggle = root.querySelector("[data-cinema-toggle]");
        var replay = root.querySelector("[data-cinema-replay]");
        var soundButton = root.querySelector("[data-cinema-sound]");
        var startButton = root.querySelector("[data-cinema-start]");
        var progressBar = root.querySelector(".dbx-cinema-progress");
        var progress = root.querySelector("[data-cinema-progress]");
        var time = root.querySelector("[data-cinema-time]");
        var elapsed = 0;
        var startedAt = 0;
        var frame = 0;
        var playing = false;
        var pausedByUser = false;
        var pausedByVisibility = false;
        var soundEnabled = false;
        var soundscape = null;
        var nextSoundCue = 0;
        var soundCues = [
            { second: 3, frequency: 146.83 },
            { second: 29, frequency: 196 },
            { second: 54, frequency: 246.94 },
            { second: 69, frequency: 440 },
            { second: 81, frequency: 659.25 }
        ];

        root.style.setProperty("--dbx-cinema-duration", duration + "s");

        function updateProgress() {
            var current = Math.min(duration, elapsed);
            var percent = (current / duration) * 100;
            if (progress) progress.style.width = percent + "%";
            if (progressBar) progressBar.setAttribute("aria-valuenow", String(Math.round(current)));
            if (time) time.textContent = formatTime(current) + " / " + formatTime(duration);
        }

        function updateToggle(paused) {
            if (!toggle) return;
            var icon = toggle.querySelector("i");
            var label = toggle.querySelector("span");
            toggle.setAttribute("aria-label", paused ? "Animation fortsetzen" : "Animation pausieren");
            if (icon) icon.className = paused ? "bi bi-play-fill" : "bi bi-pause-fill";
            if (label) label.textContent = paused ? "Fortsetzen" : "Pause";
        }

        function updateSound() {
            if (!soundButton) return;
            var icon = soundButton.querySelector("i");
            var label = soundButton.querySelector("span");
            soundButton.setAttribute("aria-pressed", soundEnabled ? "true" : "false");
            soundButton.setAttribute(
                "aria-label",
                soundEnabled ? "Originalen Synth-Klang ausschalten" : "Originalen Synth-Klang einschalten"
            );
            if (icon) icon.className = soundEnabled ? "bi bi-volume-up-fill" : "bi bi-volume-mute-fill";
            if (label) label.textContent = soundEnabled ? "Ton aus" : "Ton starten";
        }

        function syncSound() {
            var active = soundEnabled
                && playing
                && !pausedByUser
                && !pausedByVisibility
                && !root.classList.contains("is-ended");
            root.dataset.soundState = active ? "active" : (soundEnabled ? "paused" : "off");
            if (soundscape) soundscape.setEnabled(active);
        }

        function tick(now) {
            if (!playing || pausedByUser || pausedByVisibility) return;
            elapsed = Math.min(duration, (now - startedAt) / 1000);
            updateProgress();
            while (nextSoundCue < soundCues.length
                && elapsed >= soundCues[nextSoundCue].second
            ) {
                if (soundscape && soundEnabled) {
                    soundscape.accent(soundCues[nextSoundCue].frequency, 0.28);
                }
                nextSoundCue++;
            }

            if (elapsed >= duration) {
                playing = false;
                root.classList.add("is-ended", "is-paused");
                updateToggle(true);
                syncSound();
                return;
            }
            frame = window.requestAnimationFrame(tick);
        }

        function restart() {
            window.cancelAnimationFrame(frame);
            root.classList.remove("is-playing", "is-paused", "is-ended");
            void root.offsetWidth;
            elapsed = 0;
            startedAt = window.performance.now();
            playing = true;
            pausedByUser = false;
            pausedByVisibility = false;
            nextSoundCue = 0;
            root.classList.add("is-playing");
            updateProgress();
            updateToggle(false);
            syncSound();
            frame = window.requestAnimationFrame(tick);
        }

        function pause(userInitiated) {
            if (!playing) return;
            elapsed = Math.min(duration, (window.performance.now() - startedAt) / 1000);
            if (userInitiated) pausedByUser = true;
            root.classList.add("is-paused");
            window.cancelAnimationFrame(frame);
            updateProgress();
            updateToggle(true);
            syncSound();
        }

        function resume() {
            if (reducedMotion.matches || root.classList.contains("is-ended")) {
                restart();
                return;
            }
            if (!playing) {
                restart();
                return;
            }
            pausedByUser = false;
            if (pausedByVisibility) return;
            startedAt = window.performance.now() - elapsed * 1000;
            root.classList.remove("is-paused");
            updateToggle(false);
            syncSound();
            frame = window.requestAnimationFrame(tick);
        }

        function applyReducedMotion() {
            window.cancelAnimationFrame(frame);
            playing = false;
            elapsed = duration;
            root.classList.remove("is-playing", "is-paused");
            root.classList.add("is-ended", "is-reduced-motion");
            updateProgress();
            syncSound();
        }

        if (toggle) {
            toggle.addEventListener("click", function () {
                if (pausedByUser || !playing) {
                    resume();
                } else {
                    pause(true);
                }
            });
        }

        if (replay) replay.addEventListener("click", restart);
        if (startButton) startButton.addEventListener("click", restart);

        if (soundButton) {
            soundButton.addEventListener("click", function () {
                soundEnabled = !soundEnabled;
                if (soundEnabled && !soundscape) {
                    soundscape = createSoundscape();
                    if (!soundscape) soundEnabled = false;
                }
                updateSound();
                syncSound();
            });
        }

        document.addEventListener("visibilitychange", function () {
            pausedByVisibility = document.hidden;
            if (document.hidden) {
                pause(false);
            } else if (!pausedByUser && playing) {
                resume();
            }
        });

        if ("IntersectionObserver" in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.target !== root) return;
                    if (entry.isIntersecting && entry.intersectionRatio >= 0.25) {
                        pausedByVisibility = false;
                        if (!playing && !root.classList.contains("is-ended")) {
                            restart();
                        } else if (!pausedByUser && playing) {
                            resume();
                        }
                    } else if (playing) {
                        pausedByVisibility = true;
                        pause(false);
                    }
                });
            }, { threshold: [0, 0.25, 0.75] });
            observer.observe(root);
        } else if (!reducedMotion.matches) {
            restart();
        }

        if (typeof reducedMotion.addEventListener === "function") {
            reducedMotion.addEventListener("change", function (event) {
                if (event.matches) applyReducedMotion();
            });
        }

        window.addEventListener("pagehide", function () {
            if (soundscape) soundscape.destroy();
        }, { once: true });

        updateSound();
        updateProgress();
        if (reducedMotion.matches) applyReducedMotion();

        root.dbxCinemaController = {
            restart: restart,
            pause: function () { pause(true); },
            resume: resume
        };
    }

    document.querySelectorAll("[data-dbx-cinema]").forEach(initialize);
})(window, document);

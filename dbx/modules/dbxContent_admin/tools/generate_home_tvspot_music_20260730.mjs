import fs from 'node:fs';
import path from 'node:path';

const outputFile = process.argv[2];
if (!outputFile) {
  throw new Error('Aufruf: node generate_home_tvspot_music_20260730.mjs <ausgabe.wav>');
}

const sampleRate = 48000;
const duration = 30;
const sampleCount = sampleRate * duration;
const bpm = 128;
const beatDuration = 60 / bpm;
const left = new Float64Array(sampleCount);
const right = new Float64Array(sampleCount);

let randomState = 0x5db4a93f;
function random() {
  randomState ^= randomState << 13;
  randomState ^= randomState >>> 17;
  randomState ^= randomState << 5;
  return ((randomState >>> 0) / 0xffffffff) * 2 - 1;
}

function midi(note) {
  return 440 * Math.pow(2, (note - 69) / 12);
}

function addSample(index, value, pan = 0) {
  if (index < 0 || index >= sampleCount) return;
  const leftGain = Math.sqrt((1 - pan) * 0.5);
  const rightGain = Math.sqrt((1 + pan) * 0.5);
  left[index] += value * leftGain;
  right[index] += value * rightGain;
}

function addKick(start, amplitude = 0.95) {
  const length = Math.floor(sampleRate * 0.34);
  let phase = 0;
  const startIndex = Math.floor(start * sampleRate);
  for (let i = 0; i < length; i++) {
    const p = i / length;
    const frequency = 48 + 118 * Math.exp(-p * 14);
    phase += (Math.PI * 2 * frequency) / sampleRate;
    const envelope = Math.pow(1 - p, 3.3);
    const click = i < sampleRate * 0.012 ? random() * (1 - i / (sampleRate * 0.012)) * 0.18 : 0;
    addSample(startIndex + i, (Math.sin(phase) * envelope + click) * amplitude, 0);
  }
}

function addHat(start, amplitude = 0.16, open = false, pan = 0) {
  const seconds = open ? 0.22 : 0.075;
  const length = Math.floor(sampleRate * seconds);
  const startIndex = Math.floor(start * sampleRate);
  let previousNoise = 0;
  for (let i = 0; i < length; i++) {
    const p = i / length;
    const noise = random();
    const highPassed = noise - previousNoise * 0.92;
    previousNoise = noise;
    const envelope = Math.exp(-p * (open ? 5.2 : 11));
    addSample(startIndex + i, highPassed * envelope * amplitude, pan);
  }
}

function addClap(start, amplitude = 0.24) {
  const bursts = [0, 0.018, 0.038];
  for (const delay of bursts) {
    const length = Math.floor(sampleRate * 0.12);
    const startIndex = Math.floor((start + delay) * sampleRate);
    let previousNoise = 0;
    for (let i = 0; i < length; i++) {
      const p = i / length;
      const noise = random();
      const highPassed = noise - previousNoise * 0.78;
      previousNoise = noise;
      const body = Math.sin((Math.PI * 2 * 190 * i) / sampleRate) * 0.12;
      addSample(startIndex + i, (highPassed * 0.7 + body) * Math.exp(-p * 7) * amplitude, 0);
    }
  }
}

function addBass(start, note, seconds, amplitude = 0.28) {
  const frequency = midi(note);
  const length = Math.floor(sampleRate * seconds);
  const startIndex = Math.floor(start * sampleRate);
  let phase = 0;
  for (let i = 0; i < length; i++) {
    const p = i / length;
    phase += (Math.PI * 2 * frequency) / sampleRate;
    const attack = Math.min(1, i / (sampleRate * 0.012));
    const release = Math.pow(1 - p, 0.9);
    const wave = Math.sin(phase) + Math.sin(phase * 2) * 0.24 + Math.sin(phase * 3) * 0.08;
    const absoluteTime = start + i / sampleRate;
    const beatPhase = (absoluteTime % beatDuration) / beatDuration;
    const duck = beatPhase < 0.22 ? 0.32 + beatPhase * 3.1 : 1;
    addSample(startIndex + i, wave * attack * release * duck * amplitude, -0.04);
  }
}

function addPluck(start, note, seconds, amplitude = 0.09, pan = 0) {
  const frequency = midi(note);
  const length = Math.floor(sampleRate * seconds);
  const startIndex = Math.floor(start * sampleRate);
  let phase = 0;
  for (let i = 0; i < length; i++) {
    const p = i / length;
    phase += (Math.PI * 2 * frequency) / sampleRate;
    const triangle = (2 / Math.PI) * Math.asin(Math.sin(phase));
    const shimmer = Math.sin(phase * 2.01) * 0.28 + Math.sin(phase * 4.02) * 0.1;
    const envelope = Math.exp(-p * 4.2) * Math.min(1, i / (sampleRate * 0.006));
    addSample(startIndex + i, (triangle * 0.72 + shimmer) * envelope * amplitude, pan);
  }
}

function addChord(start, notes, seconds, amplitude = 0.07) {
  const length = Math.floor(sampleRate * seconds);
  const startIndex = Math.floor(start * sampleRate);
  const phases = notes.map(() => 0);
  for (let i = 0; i < length; i++) {
    const p = i / length;
    const attack = Math.min(1, i / (sampleRate * 0.08));
    const release = Math.min(1, (length - i) / (sampleRate * 0.2));
    let value = 0;
    for (let n = 0; n < notes.length; n++) {
      const frequency = midi(notes[n]);
      phases[n] += (Math.PI * 2 * frequency) / sampleRate;
      value += Math.sin(phases[n]) * 0.65 + Math.sin(phases[n] * 2) * 0.12;
    }
    value /= notes.length;
    const slowPulse = 0.72 + Math.sin(Math.PI * 2 * 0.5 * (start + i / sampleRate)) * 0.12;
    addSample(startIndex + i, value * attack * release * slowPulse * amplitude, -0.22);
    addSample(startIndex + i + 360, value * attack * release * slowPulse * amplitude * 0.7, 0.42);
  }
}

function addRiser(start, seconds, amplitude = 0.09) {
  const length = Math.floor(sampleRate * seconds);
  const startIndex = Math.floor(start * sampleRate);
  let previousNoise = 0;
  for (let i = 0; i < length; i++) {
    const p = i / length;
    const noise = random();
    const highPassed = noise - previousNoise * (0.98 - p * 0.3);
    previousNoise = noise;
    const envelope = p * p * (1 - Math.max(0, p - 0.92) / 0.08);
    addSample(startIndex + i, highPassed * envelope * amplitude, -0.55 + p * 1.1);
  }
}

const progression = [
  { chord: [60, 64, 67], bass: 36 },
  { chord: [65, 69, 72], bass: 41 },
  { chord: [57, 60, 64], bass: 33 },
  { chord: [55, 59, 62], bass: 31 }
];
const bassPattern = [0, 0, 12, 7, 0, 7, 12, 7];
const arpPattern = [0, 1, 2, 1, 2, 1, 0, 1];

const totalBeats = Math.ceil(duration / beatDuration);
for (let beat = 0; beat < totalBeats; beat++) {
  const time = beat * beatDuration;
  addKick(time, beat % 4 === 0 ? 1 : 0.88);
  if (beat % 4 === 1 || beat % 4 === 3) addClap(time, 0.28);
  addHat(time, 0.12, false, beat % 2 === 0 ? -0.28 : 0.28);
  addHat(time + beatDuration / 2, time > 7.5 ? 0.16 : 0.1, time > 22 && beat % 2 === 1, beat % 2 === 0 ? 0.36 : -0.36);
}

const bars = Math.ceil(duration / (beatDuration * 4));
for (let bar = 0; bar < bars; bar++) {
  const barTime = bar * beatDuration * 4;
  const harmony = progression[bar % progression.length];
  addChord(barTime, harmony.chord, beatDuration * 3.85, bar < 2 ? 0.052 : 0.072);
  if (bar > 0 && bar % 4 === 3) addRiser(barTime + beatDuration * 2.1, beatDuration * 1.9, 0.085);

  for (let step = 0; step < 8; step++) {
    const time = barTime + step * beatDuration * 0.5;
    const bassNote = harmony.bass + bassPattern[step];
    addBass(time, bassNote, beatDuration * 0.46, step % 4 === 0 ? 0.31 : 0.24);
  }

  for (let step = 0; step < 16; step++) {
    const time = barTime + step * beatDuration * 0.25;
    const chordIndex = arpPattern[step % arpPattern.length];
    const octave = time > 15 && step % 4 === 3 ? 12 : 0;
    const note = harmony.chord[chordIndex] + 12 + octave;
    const pan = ((step % 4) - 1.5) / 3 * 0.65;
    addPluck(time, note, beatDuration * 0.42, time < 2 ? 0.055 : 0.08, pan);
  }
}

let peak = 0;
for (let i = 0; i < sampleCount; i++) {
  const fadeIn = Math.min(1, i / (sampleRate * 0.08));
  const fadeOut = Math.min(1, (sampleCount - i) / (sampleRate * 0.75));
  const masterEnvelope = fadeIn * fadeOut;
  left[i] = Math.tanh(left[i] * 1.25) * masterEnvelope;
  right[i] = Math.tanh(right[i] * 1.25) * masterEnvelope;
  peak = Math.max(peak, Math.abs(left[i]), Math.abs(right[i]));
}

const normalization = peak > 0 ? 0.94 / peak : 1;
const dataBytes = sampleCount * 4;
const wav = Buffer.alloc(44 + dataBytes);
wav.write('RIFF', 0);
wav.writeUInt32LE(36 + dataBytes, 4);
wav.write('WAVE', 8);
wav.write('fmt ', 12);
wav.writeUInt32LE(16, 16);
wav.writeUInt16LE(1, 20);
wav.writeUInt16LE(2, 22);
wav.writeUInt32LE(sampleRate, 24);
wav.writeUInt32LE(sampleRate * 4, 28);
wav.writeUInt16LE(4, 32);
wav.writeUInt16LE(16, 34);
wav.write('data', 36);
wav.writeUInt32LE(dataBytes, 40);

for (let i = 0; i < sampleCount; i++) {
  const offset = 44 + i * 4;
  wav.writeInt16LE(Math.max(-32768, Math.min(32767, Math.round(left[i] * normalization * 32767))), offset);
  wav.writeInt16LE(Math.max(-32768, Math.min(32767, Math.round(right[i] * normalization * 32767))), offset + 2);
}

fs.mkdirSync(path.dirname(path.resolve(outputFile)), { recursive: true });
fs.writeFileSync(outputFile, wav);
console.log(`Originaltrack: ${path.resolve(outputFile)} (${duration}s, ${bpm} BPM, ${sampleRate} Hz)`);

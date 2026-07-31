import fs from 'node:fs';
import path from 'node:path';

const outputFile = process.argv[2];
if (!outputFile) {
  throw new Error('Aufruf: node generate_home_tvspot_music_v3_20260730.mjs <ausgabe.wav>');
}

const sampleRate = 48000;
const duration = 30;
const sampleCount = sampleRate * duration;
const bpm = 126;
const beatDuration = 60 / bpm;
const sixteenth = beatDuration / 4;
const left = new Float64Array(sampleCount);
const right = new Float64Array(sampleCount);

let randomState = 0x4d3c2b1a;
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

function sidechainAt(seconds) {
  const phase = (seconds % beatDuration) / beatDuration;
  if (phase >= 0.29) return 1;
  return 0.24 + phase * 2.62;
}

function addKick(start, amplitude = 0.96) {
  const length = Math.floor(sampleRate * 0.32);
  const startIndex = Math.floor(start * sampleRate);
  let phase = 0;
  for (let i = 0; i < length; i++) {
    const p = i / length;
    const frequency = 47 + 132 * Math.exp(-p * 15);
    phase += (Math.PI * 2 * frequency) / sampleRate;
    const envelope = Math.pow(1 - p, 3.6);
    const clickLength = sampleRate * 0.008;
    const click = i < clickLength ? random() * (1 - i / clickLength) * 0.16 : 0;
    addSample(startIndex + i, (Math.sin(phase) * envelope + click) * amplitude, 0);
  }
}

function addClap(start, amplitude = 0.22) {
  for (const delay of [0, 0.017, 0.034]) {
    const length = Math.floor(sampleRate * 0.11);
    const startIndex = Math.floor((start + delay) * sampleRate);
    let previous = 0;
    for (let i = 0; i < length; i++) {
      const p = i / length;
      const noise = random();
      const high = noise - previous * 0.82;
      previous = noise;
      const body = Math.sin((Math.PI * 2 * 215 * i) / sampleRate) * 0.1;
      addSample(startIndex + i, (high * 0.72 + body) * Math.exp(-p * 8.2) * amplitude, 0);
    }
  }
}

function addHat(start, amplitude = 0.12, open = false, pan = 0) {
  const length = Math.floor(sampleRate * (open ? 0.2 : 0.052));
  const startIndex = Math.floor(start * sampleRate);
  let previous = 0;
  for (let i = 0; i < length; i++) {
    const p = i / length;
    const noise = random();
    const high = noise - previous * 0.95;
    previous = noise;
    const envelope = Math.exp(-p * (open ? 5.3 : 13));
    addSample(startIndex + i, high * envelope * amplitude, pan);
  }
}

function addSequencerStep(start, note, amplitude = 0.22, pan = 0) {
  const frequency = midi(note);
  const length = Math.floor(sampleRate * sixteenth * 0.92);
  const startIndex = Math.floor(start * sampleRate);
  let phase = 0;
  for (let i = 0; i < length; i++) {
    const p = i / length;
    phase += (Math.PI * 2 * frequency) / sampleRate;
    const absoluteTime = start + i / sampleRate;
    const attack = Math.min(1, i / (sampleRate * 0.004));
    const release = Math.pow(1 - p, 0.7);
    const cutoffSweep = 0.18 + 0.82 * Math.sin(Math.PI * Math.min(1, p * 1.35));
    let wave = 0;
    for (let harmonic = 1; harmonic <= 7; harmonic++) {
      const harmonicGain = Math.pow(0.58, harmonic - 1) * Math.pow(cutoffSweep, harmonic * 0.42);
      wave += Math.sin(phase * harmonic + harmonic * 0.13) * harmonicGain;
    }
    const sub = Math.sin(phase * 0.5) * 0.26;
    const duck = sidechainAt(absoluteTime);
    addSample(
      startIndex + i,
      (wave * 0.52 + sub) * attack * release * duck * amplitude,
      pan
    );
  }
}

function addDiscoPad(start, notes, seconds, amplitude = 0.055) {
  const length = Math.floor(sampleRate * seconds);
  const startIndex = Math.floor(start * sampleRate);
  const phases = notes.map(() => 0);
  for (let i = 0; i < length; i++) {
    const p = i / length;
    const absoluteTime = start + i / sampleRate;
    const attack = Math.min(1, i / (sampleRate * 0.16));
    const release = Math.min(1, (length - i) / (sampleRate * 0.28));
    const shimmer = 0.76 + 0.24 * Math.sin(Math.PI * 2 * 0.18 * absoluteTime);
    let value = 0;
    for (let n = 0; n < notes.length; n++) {
      const frequency = midi(notes[n]);
      phases[n] += (Math.PI * 2 * frequency) / sampleRate;
      value += Math.sin(phases[n]) * 0.58;
      value += Math.sin(phases[n] * 2.004 + n * 0.4) * 0.13;
      value += Math.sin(phases[n] * 3.007 + n * 0.7) * 0.05;
    }
    value /= notes.length;
    const duck = sidechainAt(absoluteTime);
    addSample(startIndex + i, value * attack * release * shimmer * duck * amplitude, -0.34);
    addSample(startIndex + i + 410, value * attack * release * shimmer * duck * amplitude * 0.78, 0.42);
  }
}

function addSpark(start, note, amplitude = 0.075, pan = 0) {
  const frequency = midi(note);
  const length = Math.floor(sampleRate * 0.17);
  const startIndex = Math.floor(start * sampleRate);
  let phase = 0;
  for (let i = 0; i < length; i++) {
    const p = i / length;
    phase += (Math.PI * 2 * frequency) / sampleRate;
    const tone = Math.sin(phase) * 0.66 + Math.sin(phase * 2.01) * 0.24 + Math.sin(phase * 4.03) * 0.1;
    addSample(startIndex + i, tone * Math.exp(-p * 6.2) * amplitude, pan);
  }
}

function addRiser(start, seconds, amplitude = 0.085) {
  const length = Math.floor(sampleRate * seconds);
  const startIndex = Math.floor(start * sampleRate);
  let previous = 0;
  for (let i = 0; i < length; i++) {
    const p = i / length;
    const noise = random();
    const high = noise - previous * (0.985 - p * 0.35);
    previous = noise;
    const tail = p > 0.93 ? Math.max(0, 1 - (p - 0.93) / 0.07) : 1;
    addSample(startIndex + i, high * p * p * tail * amplitude, -0.65 + p * 1.3);
  }
}

const progression = [
  { chord: [57, 60, 64], bass: 33 },
  { chord: [53, 57, 60], bass: 29 },
  { chord: [60, 64, 67], bass: 36 },
  { chord: [55, 59, 62], bass: 31 }
];
const sequence = [0, 12, 7, 12, 3, 12, 7, 15, 0, 12, 10, 12, 7, 15, 12, 19];
const bars = Math.ceil(duration / (beatDuration * 4));

for (let beat = 0; beat < Math.ceil(duration / beatDuration); beat++) {
  const time = beat * beatDuration;
  addKick(time, beat % 4 === 0 ? 1 : 0.91);
  if (beat % 4 === 1 || beat % 4 === 3) addClap(time, 0.23);
  addHat(time, 0.09, false, beat % 2 === 0 ? -0.26 : 0.26);
  addHat(
    time + beatDuration / 2,
    time > 3.6 ? 0.145 : 0.1,
    time > 17 && beat % 4 === 3,
    beat % 2 === 0 ? 0.38 : -0.38
  );
}

for (let bar = 0; bar < bars; bar++) {
  const barStart = bar * beatDuration * 4;
  const harmony = progression[bar % progression.length];
  const energy = bar < 2 ? 0.72 : bar < 8 ? 0.92 : 1;
  addDiscoPad(barStart, harmony.chord, beatDuration * 3.92, 0.052 * energy);

  for (let step = 0; step < 16; step++) {
    const time = barStart + step * sixteenth;
    const note = harmony.bass + sequence[step];
    const accent = step % 4 === 0 ? 1.08 : step % 2 === 1 ? 0.9 : 1;
    const pan = ((step % 4) - 1.5) * 0.08;
    addSequencerStep(time, note, 0.205 * energy * accent, pan);
  }

  if (bar >= 2) {
    for (let step = 0; step < 8; step++) {
      const time = barStart + (step + 0.5) * beatDuration * 0.5;
      const note = harmony.chord[(step * 2 + bar) % harmony.chord.length] + 24;
      const pan = step % 2 === 0 ? -0.48 : 0.48;
      addSpark(time, note, 0.05 + (bar >= 6 ? 0.016 : 0), pan);
    }
  }

  if (bar > 0 && bar % 4 === 3) {
    addRiser(barStart + beatDuration * 2.15, beatDuration * 1.85, 0.09);
  }
}

let peak = 0;
for (let i = 0; i < sampleCount; i++) {
  const fadeIn = Math.min(1, i / (sampleRate * 0.06));
  const fadeOut = Math.min(1, (sampleCount - i) / (sampleRate * 0.7));
  const master = fadeIn * fadeOut;
  left[i] = Math.tanh(left[i] * 1.38) * master;
  right[i] = Math.tanh(right[i] * 1.38) * master;
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
console.log(`Originaler Electro-Disco-Track: ${path.resolve(outputFile)} (${duration}s, ${bpm} BPM, ${sampleRate} Hz)`);

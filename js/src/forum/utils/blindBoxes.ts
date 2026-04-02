export interface BlindBoxPowResult {
  nonce: string;
  hash: string;
  zeros: number;
  attempts: number;
}

export interface BlindBoxPowProgress {
  zeros: number;
  attempts: number;
  secondsLeft: number;
  rarity: string;
}

export function blindBoxId(box: any): string | null {
  if (!box) return null;
  if (typeof box.id === 'function') return String(box.id());
  if (box.id !== undefined && box.id !== null) return String(box.id);
  if (box.data?.id !== undefined && box.data?.id !== null) return String(box.data.id);
  return null;
}

export function blindBoxSeed(box: any): string | null {
  return box?.seed?.() || box?.attributes?.seed || null;
}

export function blindBoxBudget(box: any): number | null {
  const value = box?.budget?.() ?? box?.attributes?.budget ?? null;
  return typeof value === 'number' ? value : null;
}

export function rarityFromZeros(zeros: number): string {
  if (zeros >= 7) return 'legendary';
  if (zeros >= 6) return 'epic';
  if (zeros >= 5) return 'rare';
  return 'common';
}

export function rarityFromBudget(budget: number): string {
  if (budget >= 160) return 'legendary';
  if (budget >= 80) return 'epic';
  if (budget >= 40) return 'rare';
  return 'common';
}

export async function computeBlindBoxPow(
  seed: string,
  durationMs: number = 10_000,
  onProgress?: (progress: BlindBoxPowProgress) => void
): Promise<BlindBoxPowResult> {
  const deadline = performance.now() + durationMs;
  const uiUpdateIntervalMs = 200;
  const yieldEvery = 100;

  let nonce = '0';
  let hash = await sha256Hex(seed + nonce);
  let bestZeros = countLeadingZeros(hash);
  let attempts = 1;
  let lastUiUpdate = performance.now();

  emitProgress(deadline, bestZeros, attempts, onProgress);

  while (performance.now() < deadline) {
    const candidateNonce = attempts.toString(16);
    const candidateHash = await sha256Hex(seed + candidateNonce);
    attempts++;
    const zeros = countLeadingZeros(candidateHash);

    if (zeros > bestZeros) {
      nonce = candidateNonce;
      hash = candidateHash;
      bestZeros = zeros;
    }

    const now = performance.now();
    if (now - lastUiUpdate >= uiUpdateIntervalMs) {
      emitProgress(deadline, bestZeros, attempts, onProgress);
      lastUiUpdate = now;
    }

    if (attempts % yieldEvery === 0) {
      await yieldToBrowser();
    }
  }

  emitProgress(deadline, bestZeros, attempts, onProgress);

  return { nonce, hash, zeros: bestZeros, attempts };
}

function emitProgress(
  deadline: number,
  zeros: number,
  attempts: number,
  onProgress?: (progress: BlindBoxPowProgress) => void
) {
  if (!onProgress) {
    return;
  }

  onProgress({
    zeros,
    attempts,
    secondsLeft: Math.max(0, Math.ceil((deadline - performance.now()) / 1000)),
    rarity: rarityFromZeros(zeros),
  });
}

async function yieldToBrowser(): Promise<void> {
  await new Promise((resolve) => setTimeout(resolve, 0));
}

async function sha256Hex(value: string): Promise<string> {
  const bytes = new TextEncoder().encode(value);
  const digest = await crypto.subtle.digest('SHA-256', bytes);

  return Array.from(new Uint8Array(digest))
    .map((byte) => byte.toString(16).padStart(2, '0'))
    .join('');
}

function countLeadingZeros(hash: string): number {
  let count = 0;

  while (count < hash.length && hash[count] === '0') {
    count++;
  }

  return count;
}

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const TEST_MNEMONIC = 'test test test test test test test test test test test junk';
const ACCOUNT_INDEX_BY_ADDRESS: Record<string, number> = {
  '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266': 0,
  '0x70997970c51812dc3a010c7d01b50e0d17dc79c8': 1,
  '0x3c44cdddb6a900fa2b585dd299e03d12fa4293bc': 2,
};

function normalizeAddress(value: string): string {
  return value.trim().toLowerCase();
}

async function signWithCast(message: string, address: string): Promise<string> {
  const normalizedAddress = normalizeAddress(address);
  const mnemonicIndex = ACCOUNT_INDEX_BY_ADDRESS[normalizedAddress];

  if (mnemonicIndex === undefined) {
    throw new Error(`Unsupported mock wallet address: ${address}`);
  }

  const { stdout } = await execFileAsync('cast', [
    'wallet',
    'sign',
    '--mnemonic',
    TEST_MNEMONIC,
    '--mnemonic-index',
    String(mnemonicIndex),
    message,
  ]);

  return stdout.trim();
}

export default async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 960 });

  await page.exposeFunction('__pwMockPersonalSign', async (message: string, address: string) => {
    return await signWithCast(message, address);
  });
};

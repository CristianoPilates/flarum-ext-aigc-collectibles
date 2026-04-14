import { transText } from './i18n';

const DEFAULT_COLLECTIBLE_NAME = /^Collectible #(.+)$/i;

// Rarity tier for sorting — higher number = more rare = sort first
export const RARITY_TIER: Record<string, number> = {
  legendary: 4,
  epic: 3,
  rare: 2,
  common: 1,
};

export function collectibleRarityTier(rarity?: string | null): number {
  return RARITY_TIER[rarity || 'common'] ?? 0;
}

export function collectibleLabel(id?: string | number | null): string {
  const displayId = id === null || id === undefined || id === '' ? '?' : String(id);

  return transText('donk-aigc-collectibles.forum.collectible.name_fallback', { idLabel: `#${displayId}` });
}

export function displayCollectibleName(name?: string | null, id?: string | number | null): string {
  const trimmed = typeof name === 'string' ? name.trim() : '';

  if (trimmed === '') {
    return collectibleLabel(id);
  }

  const defaultName = trimmed.match(DEFAULT_COLLECTIBLE_NAME);

  if (!defaultName) {
    return trimmed;
  }

  return collectibleLabel(defaultName[1] || id);
}

export function collectibleRarityLabel(rarity?: string | null): string {
  return transText('donk-aigc-collectibles.forum.collectible.rarity_' + (rarity || 'common'));
}

import type { BarterAsset } from './barterComposer';

/**
 * Sort assets by rarity tier descending (legendary first).
 * Blind boxes are appended after collectibles within the same tier.
 */
export function sortAssetsByRarity(assets: BarterAsset[]): BarterAsset[] {
  return [...assets].sort((a, b) => {
    const rarityDiff = collectibleRarityTier(b.rarity) - collectibleRarityTier(a.rarity);
    if (rarityDiff !== 0) return rarityDiff;
    // Within the same rarity tier, blind boxes after collectibles
    if (a.assetType === 'collectible' && b.assetType === 'blind_box') return -1;
    if (a.assetType === 'blind_box' && b.assetType === 'collectible') return 1;
    return 0;
  });
}

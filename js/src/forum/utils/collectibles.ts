import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';

const DEFAULT_COLLECTIBLE_NAME = /^Collectible #(.+)$/i;

export function collectibleLabel(id?: string | number | null): string {
  const displayId = id === null || id === undefined || id === '' ? '?' : String(id);

  return extractText(
    app.translator.trans('donk-aigc-collectibles.forum.collectible.name_fallback', { idLabel: `#${displayId}` })
  );
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
  return extractText(app.translator.trans('donk-aigc-collectibles.forum.collectible.rarity_' + (rarity || 'common')));
}

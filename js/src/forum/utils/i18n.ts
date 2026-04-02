import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';

export function transText(key: string, parameters: Record<string, unknown> = {}): string {
  return extractText(app.translator.trans(key, parameters));
}

export function displayUserName(user: any): string {
  if (!user) {
    return '';
  }

  if (typeof user.displayName === 'function') {
    return user.displayName();
  }

  if (typeof user.username === 'function') {
    return user.username();
  }

  if (typeof user.attribute === 'function') {
    return user.attribute('displayName') || user.attribute('username') || '';
  }

  return user.displayName || user.username || '';
}

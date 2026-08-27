import app from 'flarum/forum/app';

const COOKIE_NAME = 'sb_device_id';
const LS_KEY = 'sb_device_id';

function getCookie(name) {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
  return match ? decodeURIComponent(match[1]) : null;
}

function setCookie(name, value) {
  const expires = new Date(Date.now() + 365 * 86400000).toUTCString();
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/; expires=${expires}; SameSite=Lax; Secure`;
}

// Restore device ID from localStorage if cookie was cleared
function syncDeviceId() {
  try {
    const cookieVal = getCookie(COOKIE_NAME);
    const storedVal = localStorage.getItem(LS_KEY);

    if (cookieVal) {
      localStorage.setItem(LS_KEY, cookieVal);
    } else if (storedVal) {
      setCookie(COOKIE_NAME, storedVal);
    }
  } catch (e) {
    // localStorage unavailable (private mode, etc.)
  }
}

app.initializers.add('bigreja-device-tracker', () => {
  syncDeviceId();
});
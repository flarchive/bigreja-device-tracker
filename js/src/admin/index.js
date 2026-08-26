import app from 'flarum/admin/app';
import SharedDevicesPage from './components/SharedDevicesPage';

app.initializers.add('superbraga-device-tracker', () => {
  app.extensionData
    .for('superbraga-device-tracker')
    .registerPage(SharedDevicesPage);
});
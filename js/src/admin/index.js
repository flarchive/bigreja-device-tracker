import app from 'flarum/admin/app';
import SharedDevicesPage from './components/SharedDevicesPage';

app.initializers.add('bigreja-device-tracker', () => {
  app.extensionData
    .for('bigreja-device-tracker')
    .registerPage(SharedDevicesPage);
});
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Stream from 'flarum/common/utils/Stream';

export default class SharedDevicesPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.devices = [];
    this.searchQuery = Stream('');
    this.page = 1;
    this.totalPages = 1;

    this.loadData();
  }

  loadData() {
    this.loading = true;
    app.request({
      method: 'GET',
      url: app.forum.attribute('apiUrl') + '/device-tracker/shared',
      params: {
        q: this.searchQuery(),
        page: this.page,
      },
    }).then((response) => {
      this.devices = response.data;
      this.totalPages = response.meta.pages || 1;
      this.loading = false;
      m.redraw();
    });
  }

  content() {
    return (
      <div className="ExtensionPage-settings DeviceTrackerPage">
        <div className="container">
          <h2>Dispositivos com Múltiplas Contas Registadas</h2>
          <p className="helpText">
            Lista de <code>device_uuid</code> associados a dois ou mais utilizadores no fórum.
          </p>

          <div className="Form-group" style={{ display: 'flex', gap: '10px', marginBottom: '20px' }}>
            <input
              className="FormControl"
              placeholder="Pesquisar por utilizador, UUID ou IP..."
              value={this.searchQuery()}
              oninput={(e) => this.searchQuery(e.target.value)}
              onkeydown={(e) => e.key === 'Enter' && this.loadData()}
            />
            <Button className="Button Button--primary" onclick={() => this.loadData()}>
              Filtrar
            </Button>
          </div>

          {this.loading ? (
            <LoadingIndicator />
          ) : (
            <div>
              <table className="Table" style={{ width: '100%', textAlign: 'left' }}>
                <thead>
                  <tr>
                    <th>Último Acesso</th>
                    <th>Contas Associadas</th>
                    <th>UUID do Dispositivo</th>
                    <th>Último IP</th>
                  </tr>
                </thead>
                <tbody>
                  {this.devices.length === 0 ? (
                    <tr>
                      <td colSpan="4" style={{ textAlign: 'center', padding: '20px' }}>
                        Nenhum dispositivo duplicado encontrado.
                      </td>
                    </tr>
                  ) : (
                    this.devices.map((device) => (
                      <tr key={device.device_uuid}>
                        <td>{new Date(device.last_seen_at).toLocaleString()}</td>
                        <td>
                          <strong>{device.usernames}</strong> ({device.total_users} contas)
                        </td>
                        <td>
                          <code>{device.device_uuid.substring(0, 13)}...</code>
                        </td>
                        <td>{device.last_ip}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>

              {this.totalPages > 1 && (
                <div style={{ marginTop: '15px', display: 'flex', gap: '10px', alignItems: 'center' }}>
                  <Button
                    className="Button"
                    disabled={this.page <= 1}
                    onclick={() => { this.page--; this.loadData(); }}
                  >
                    Anterior
                  </Button>
                  <span>Página {this.page} de {this.totalPages}</span>
                  <Button
                    className="Button"
                    disabled={this.page >= this.totalPages}
                    onclick={() => { this.page++; this.loadData(); }}
                  >
                    Próxima
                  </Button>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    );
  }
}
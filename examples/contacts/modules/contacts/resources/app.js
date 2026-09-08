'use strict';
(() => {
  const $ = selector => document.querySelector(selector);
  const fields = ['name','email','phone','notes'];
  let session, page = 1, query = '', record = null, busy = false, saving = false;
  const can = permission => session?.permissions.includes('contacts.' + permission);
  function message(text) { $('#status').textContent = text; }
  async function api(path, method = 'GET', data) {
    const response = await fetch('/admin/api/' + path, {method, credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':session?.csrf || ''},body:data === undefined ? undefined : JSON.stringify(data)});
    const result = await response.json();
    if (!response.ok) {
      const error = new Error(response.status === 401 ? 'Sessão encerrada. Entre novamente no painel e reabra Contatos.' : result.message || 'Falha na operação.');
      error.fields = result.errors || {}; throw error;
    }
    return result;
  }
  function button(title, callback) {
    const node = document.createElement('button'); node.type = 'button'; node.textContent = title;
    node.addEventListener('click', async () => { if (busy) return; node.disabled = true; try { await callback(); } catch (error) { message(error.message); } finally { node.disabled = false; } }); return node;
  }
  async function list() {
    if (busy) return; busy = true; $('#previous').disabled = $('#next').disabled = true;
    try {
      const result = await api('contacts?page=' + page + '&q=' + encodeURIComponent(query));
      $('#rows').replaceChildren();
      for (const item of result.data) {
        const row = document.createElement('tr');
        for (const name of ['name','email','phone']) { const cell = document.createElement('td'); cell.textContent = item[name]; row.append(cell); }
        const actions = document.createElement('td');
        actions.append(button(can('update') ? 'Editar' : 'Ver', () => edit(item.id)));
        if (can('delete')) actions.append(button('Excluir', async () => {
          if (!confirm('Excluir o contato ' + item.name + '? Esta ação não pode ser desfeita.')) return;
          await api('contacts', 'DELETE', {id:item.id,version:item.version});
          if (result.data.length === 1 && page > 1) page--;
          await list(); message('Contato excluído.');
        }));
        row.append(actions); $('#rows').append(row);
      }
      $('#page').textContent = 'Página ' + page + ' · ' + result.total + ' contato(s)';
      $('#previous').disabled = page <= 1; $('#next').disabled = page * result.per_page >= result.total;
      message(result.total ? '' : 'Nenhum contato encontrado.');
    } catch (error) { message(error.message); } finally { busy = false; }
  }
  function clearErrors() {
    $('#form-status').textContent = '';
    fields.forEach(name => { $('#error-' + name).textContent = ''; $('#contact').elements[name].removeAttribute('aria-invalid'); });
  }
  async function edit(id) {
    record = id ? await api('contacts/read', 'POST', {id}) : null;
    clearErrors(); $('#editor-title').textContent = id ? 'Detalhes do contato' : 'Novo contato';
    const writable = id ? can('update') : can('create');
    fields.forEach(name => { const input = $('#contact').elements[name]; input.value = record?.[name] || ''; input.readOnly = !writable; });
    $('#save').hidden = !writable; $('#editor').showModal();
  }
  $('#contact').addEventListener('submit', async event => {
    event.preventDefault(); if (saving || !(record ? can('update') : can('create'))) return;
    saving = true; $('#save').disabled = $('#close').disabled = true; clearErrors();
    const data = Object.fromEntries(fields.map(name => [name,$('#contact').elements[name].value]));
    try {
      await api('contacts', record ? 'PUT' : 'POST', {...data,...(record ? {id:record.id,version:record.version} : {})});
      $('#editor').close(); await list(); message('Contato salvo.');
    } catch (error) {
      $('#form-status').textContent = error.message;
      for (const name of fields) if (error.fields?.[name]) { $('#error-' + name).textContent = error.fields[name]; $('#contact').elements[name].setAttribute('aria-invalid','true'); }
      $('#contact [aria-invalid="true"]')?.focus();
    } finally { saving = false; $('#save').disabled = $('#close').disabled = false; }
  });
  $('#editor').addEventListener('cancel', event => { if (saving) event.preventDefault(); });
  $('#close').addEventListener('click', () => $('#editor').close());
  $('#new').addEventListener('click', () => edit(null).catch(error => message(error.message)));
  $('#search').addEventListener('submit', event => { event.preventDefault(); if (busy) return; page = 1; query = $('#search').elements.q.value; list(); });
  $('#previous').addEventListener('click', () => { if (!busy && page > 1) { page--; list(); } });
  $('#next').addEventListener('click', () => { if (!busy) { page++; list(); } });
  (async () => {
    try { session = await api('session'); if (!session.user) throw new Error('Entre no painel para usar Contatos.'); if (!can('view') || !session.permissions.includes('dashboard.view')) throw new Error('Seu perfil não permite consultar contatos.'); $('#new').hidden = !can('create'); await list(); }
    catch (error) { message(error.message); }
  })();
})();

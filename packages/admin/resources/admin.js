'use strict';
(() => {
  const $ = selector => document.querySelector(selector);
  const el = (tag, text = null, attrs = {}) => { const node = document.createElement(tag); if (text !== null) node.textContent = text; Object.assign(node, attrs); return node; };
  let session = {user:null,permissions:[]};
  let resetToken = new URLSearchParams(location.hash.slice(1)).get('reset');
  if (resetToken) history.replaceState(null, '', '/admin');
  const windows = new FxWindowManager({updateHash:false}).start();
  const can = permission => session.permissions.includes(permission);
  function notice(text, error = false) { $('#notice').textContent = text; $('#notice').classList.toggle('error', error); }
  async function api(path, data) {
    const response = await fetch('/admin/api/' + path, {method:data===undefined?'GET':'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':session.csrf||''},body:data===undefined?undefined:JSON.stringify(data)});
    const result = await response.json();
    if (!response.ok) { if(response.status===401 && session.user) location.reload(); throw Error(result.message || 'Não foi possível concluir.'); }
    if (result.csrf) session.csrf = result.csrf;
    return result;
  }
  function action(button, callback, status = $('#notice')) {
    button.addEventListener('click', async () => { button.disabled=true; try { await callback(); } catch(error) { status.textContent=error.message; } finally { button.disabled=false; } });
    return button;
  }
  function formSubmit(form, callback, status) {
    form.addEventListener('submit', async event => { event.preventDefault(); const button=form.querySelector('button'); if(button.disabled)return; button.disabled=true; status.textContent=''; try { await callback(); } catch(error) { status.textContent=error.message; } finally { button.disabled=false; } });
  }
  function field(form,name,title,type='text',value='') {
    const label=el('label',title), input=el('input',null,{name,type,value}); label.append(input); form.append(label); return input;
  }
  function workspace(id,title,level=1) { const box=el('div',null,{className:'workspace'}); windows.open({id,title,element:box,width:920,height:620,level}); const topic=id.startsWith('user')?'users':id.startsWith('role')?'roles':'modules';box.append(el('a','Ajuda desta etapa',{href:'/admin/help#'+topic,target:'_blank',rel:'noopener'})); return box; }
  function status(box) { const node=el('p','');node.setAttribute('role','status');box.append(node);return node; }
  function table(box,headers) { const wrap=el('div',null,{className:'table-scroll'}), table=el('table'),head=el('thead'),row=el('tr'),body=el('tbody'); headers.forEach(text=>row.append(el('th',text)));head.append(row);table.append(head,body);wrap.append(table);box.append(wrap);return body; }
  async function loadSession() {
    session=await api('session');
    $('#identity').textContent=session.user?.name||'';$('#logout').hidden=!session.user;
    $('#access').hidden=!!session.user||!!resetToken;$('#dashboard').hidden=!session.user||!can('dashboard.view')||!!resetToken;$('#reset').hidden=!resetToken;$('#recovery').hidden=!session.recovery;
    $('#title').textContent=session.user?'Seu trabalho, em um só lugar.':'Bem-vindo ao FX.';
    for(const [area,permission] of Object.entries({users:'users.view',roles:'roles.manage',modules:'modules.view'})) $('[data-area='+area+']').hidden=!can(permission);
    notice(session.user?(can('dashboard.view')?'Escolha uma área abaixo.':'Seu perfil não permite acesso ao painel. Contate um administrador.'):'Entre com seu email e senha.');
  }
  async function userEditor(user={}) {
    const roles=await api('roles'),box=workspace('user-edit',user.id?'Editar usuário':'Novo usuário',2),form=el('form');box.append(form);
    const name=field(form,'name','Nome','text',user.name||'');name.required=true;name.maxLength=120;
    const email=field(form,'email','Email','email',user.email||'');email.required=true;email.maxLength=254;
    const password=field(form,'password',user.id?'Nova senha (deixe vazia para manter)':'Senha','password');password.required=!user.id;password.minLength=12;password.maxLength=72;password.autocomplete='new-password';
    const label=el('label','Perfil'),role=el('select');roles.data.forEach(item=>role.append(el('option',item.name,{value:String(item.id)})));if(user.role_id)role.value=String(user.role_id);label.append(role);form.append(label);
    const active=field(form,'active','Conta ativa','checkbox');active.checked=user.active===undefined||!!user.active;
    form.append(el('button','Salvar'));const feedback=status(box);
    formSubmit(form,async()=>{await api('users',{...(user.id?{id:Number(user.id)}:{}),name:name.value,email:email.value,password:password.value,role_id:Number(role.value),active:active.checked});password.value='';feedback.textContent='Usuário salvo.';await showUsers();},feedback);
  }
  async function showUsers(page=1,query='') {
    const result=await api('users?page='+page+'&q='+encodeURIComponent(query)),box=workspace('users','Usuários'),bar=el('div',null,{className:'toolbar'});box.append(bar);
    if(can('users.manage'))bar.append(action(el('button','Novo usuário'),()=>userEditor()));
    const search=el('form'),input=el('input',null,{value:query,placeholder:'Nome ou email',maxLength:100});input.setAttribute('aria-label','Buscar usuários');search.append(input,el('button','Buscar'));bar.append(search);const feedback=status(box);formSubmit(search,()=>showUsers(1,input.value),feedback);
    const body=table(box,['Nome','Email','Estado','Ações']);for(const user of result.data){const row=el('tr');row.append(el('td',user.name),el('td',user.email),el('td',user.active?'Ativo':'Inativo'));const cell=el('td');if(can('users.manage'))cell.append(action(el('button','Editar'),()=>userEditor(user),feedback));row.append(cell);body.append(row);}
    const pager=el('div',null,{className:'pager'}),prev=action(el('button','Anterior'),()=>showUsers(page-1,query),feedback),next=action(el('button','Próxima'),()=>showUsers(page+1,query),feedback);prev.disabled=page<=1;next.disabled=page*20>=result.total;pager.append(prev,el('span','Página '+page+' · '+result.total+' usuários'),next);box.append(pager);
  }
  async function roleEditor(role={},permissions=[]) {
    const box=workspace('role-edit',role.id?'Editar perfil':'Novo perfil',2),form=el('form');box.append(form);const name=field(form,'name','Nome do perfil','text',role.name||'');name.required=true;name.maxLength=80;
    const inputs=permissions.map(permission=>{const label=el('label',permission,{className:'check'}),input=el('input',null,{type:'checkbox',value:permission,checked:(role.permissions||[]).includes(permission)});label.prepend(input);form.append(label);return input;});form.append(el('button','Salvar perfil'));const feedback=status(box);
    formSubmit(form,async()=>{await api('roles',{...(role.id?{id:Number(role.id)}:{}),name:name.value,permissions:inputs.filter(input=>input.checked).map(input=>input.value)});feedback.textContent='Perfil salvo.';await showRoles();},feedback);
  }
  async function showRoles() {
    const result=await api('roles'),box=workspace('roles','Perfis e permissões'),feedback=status(box);box.append(action(el('button','Novo perfil'),()=>roleEditor({},result.permissions),feedback));
    const body=table(box,['Perfil','Permissões','Ações']);for(const role of result.data){const row=el('tr'),cell=el('td');if(Number(role.id)!==1)cell.append(action(el('button','Editar'),()=>roleEditor(role,result.permissions),feedback));else cell.textContent='Reservado';row.append(el('td',role.name),el('td',role.permissions.join(', ')||'Nenhuma'),cell);body.append(row);}
  }
  async function showModules() {
    const result=await api('modules'),box=workspace('modules','Módulos registrados'),feedback=status(box),body=table(box,['Módulo','Versão','Estado','Ações']);
    for(const module of result.data){const row=el('tr'),cell=el('td');if(can('modules.manage')&&module.id!=='fx-admin')cell.append(action(el('button',module.enabled?'Desativar':'Ativar'),async()=>{await api('modules',{id:module.id,enabled:!module.enabled});await showModules();},feedback));row.append(el('td',module.id),el('td',module.version),el('td',module.enabled?'Ativo':'Inativo'),cell);body.append(row);}
    box.append(el('p','Dependências são validadas pelo servidor. Mudanças valem no próximo bootstrap; workers persistentes precisam ser reiniciados.'));
  }
  formSubmit($('#login'),async()=>{const form=$('#login');await api('login',{email:form.elements.email.value,password:form.elements.password.value});form.elements.password.value='';await loadSession();},$('#notice'));
  formSubmit($('#forgot'),async()=>{const result=await api('forgot',{email:$('#forgot').elements.email.value});notice(result.message);},$('#notice'));
  formSubmit($('#reset'),async()=>{const result=await api('reset',{token:resetToken,password:$('#reset').elements.password.value});resetToken=null;$('#reset').reset();await loadSession();notice(result.message);},$('#notice'));
  action($('#logout'),async()=>{await api('logout',{});location.replace('/admin');});
  action($('[data-area=users]'),()=>showUsers());action($('[data-area=roles]'),showRoles);action($('[data-area=modules]'),showModules);
  loadSession().catch(error=>notice(error.message,true));
})();

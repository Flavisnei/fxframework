const { test } = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('node:fs');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../packages/windows/resources/fxwindows/fxwindows.js'), 'utf8');
const settle = () => new Promise(resolve => setTimeout(resolve, 0));
async function setup(html) {
  const dom = new JSDOM(html, { runScripts: 'outside-only', url: 'https://example.test' });
  dom.window.HTMLElement.prototype.scrollIntoView = function() {};
  dom.window.eval(source);
  await settle();
  return dom;
}
test('automatic startup, dynamic forms, idempotence, search and JSON value', async () => {
 const dom = await setup('<form><label>Perfil<select name="role_id"><option value="1">Administrador</option><option value="2">Editor</option></select></label><button>Salvar</button></form>');
 try {
  const w=dom.window,d=w.document,s=d.querySelector('select');
  assert.equal(d.querySelectorAll('.fx-select2').length,1);
  const widget=new w.FxSelect2(s).start();
  assert.equal(new w.FxSelect2(s).start(),widget);
  widget.button.click();widget.search.value='Editor';widget.search.dispatchEvent(new w.Event('input'));
  assert.equal(widget.list.children.length,1);widget.list.firstChild.click();
  assert.equal(JSON.stringify({role_id:Number(s.value)}),'{"role_id":2}');
  assert.equal(new w.FormData(s.form).get('role_id'),'2');
  assert.equal(widget.panel.hidden,true);
  const form=d.createElement('form');form.innerHTML='<select><option>Dinâmico</option></select>';d.body.append(form);await settle();
  assert.equal(d.querySelectorAll('.fx-select2').length,2);
  const dynamic=form.querySelector('select');form.remove();await settle();
  assert.equal(dynamic.dataset.fxSelect2Ready,undefined);
  d.body.append(form);await settle();assert.equal(form.querySelectorAll('.fx-select2').length,1);
 } finally {dom.window.close();}
});
test('disabled fields and options, mutation, reset, required and multiple selection', async () => {
 const dom=await setup('<form><fieldset><label>Itens<select required multiple name="items"><option value="a" selected>Alpha</option><optgroup disabled><option value="b">Bloqueado</option></optgroup><option value="c">Charlie</option></select></label></fieldset><button>Salvar</button></form>');
 try {
  const w=dom.window,d=w.document,s=d.querySelector('select'),widget=new w.FxSelect2(s).start();
  widget.selectValue('b');assert.deepEqual([...s.selectedOptions].map(o=>o.value),['a']);
  widget.selectValue('c');assert.deepEqual([...new w.FormData(s.form).getAll('items')],['a','c']);
  s.disabled=true;await settle();assert.equal(widget.clear.disabled,true);widget.selectValue('a');assert.equal(s.selectedOptions.length,2);
  s.disabled=false;await settle();d.querySelector('fieldset').disabled=true;await settle();assert.equal(widget.button.disabled,true);
  d.querySelector('fieldset').disabled=false;await settle();assert.equal(widget.button.disabled,false);
  widget.clear.click();assert.equal(s.selectedOptions.length,0);assert.equal(widget.button.getAttribute('aria-invalid'),'true');
  s.form.reset();await settle();assert.equal(widget.button.textContent,'Alpha');
  s.options[0].text='Atualizado';await settle();assert.equal(widget.button.textContent,'Atualizado');
  widget.open();widget.search.dispatchEvent(new w.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));assert.equal(widget.panel.hidden,true);
 } finally {dom.window.close();}
});

test('Admin submit uses Salvar even when the first select button is disabled', async () => {
 const dom=await setup('<form><select disabled><option>Perfil</option></select><button>Salvar</button></form><p id="status"></p>');
 try {
  const w=dom.window,d=w.document,form=d.querySelector('form');
  const admin=fs.readFileSync(require('node:path').join(__dirname,'../../packages/admin/resources/admin.js'),'utf8');
  w.eval(admin.slice(admin.indexOf('  function formSubmit('),admin.indexOf('  function field('))+';window.bindSubmit=formSubmit;');
  let calls=0;w.bindSubmit(form,async()=>{calls++;},d.querySelector('#status'));
  form.querySelector('button:not([type])').click();await settle();assert.equal(calls,1);
  assert.equal(form.querySelector('.fx-select2-button').disabled,true);
 } finally {dom.window.close();}
});

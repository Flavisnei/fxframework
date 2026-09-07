'use strict';
const search = document.querySelector('#search');
const sections = Array.from(document.querySelectorAll('main section'));
const status = document.querySelector('#search-status');
const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const index = sections.map(section => ({ section, text: normalize(section.textContent) }));
function filterHelp() {
  const terms = normalize(search.value).trim().split(/\s+/).filter(Boolean);
  let count = 0;
  for (const entry of index) {
    const visible = terms.every(term => entry.text.includes(term));
    entry.section.hidden = !visible;
    if (visible) count++;
  }
  status.textContent = terms.length ? `${count} de ${sections.length} tópicos encontrados.` : `${sections.length} tópicos disponíveis. A busca ignora acentos.`;
  document.querySelector('#empty').hidden = count !== 0;
}
function clearSearch() { search.value = ''; filterHelp(); }
search.addEventListener('input', filterHelp);
search.addEventListener('keydown', event => { if (event.key === 'Escape') clearSearch(); });
document.querySelector('#clear-search').addEventListener('click', () => { clearSearch(); search.focus(); });
// Links diretos continuam acessíveis mesmo quando o destino está filtrado.
document.querySelectorAll('a[href^="#"]').forEach(link => link.addEventListener('click', clearSearch));
window.addEventListener('hashchange', () => {
  clearSearch();
  const target = document.getElementById(location.hash.slice(1));
  if (target) target.scrollIntoView();
});
filterHelp();

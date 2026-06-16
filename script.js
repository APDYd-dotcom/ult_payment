// document.addEventListener('DOMContentLoaded', async () => {
//     const load = async (url) => {
//         try{
//             const res = await fetch(url);
//             if(!res.ok) return '';
//             return await res.text();
//         }catch(e){
//             return '';
//         }
//     };

//     // load sidebar into aside#sidebar
//     const sidebarHtml = await load('partials/sidebar.html');
//     const sidebarEl = document.getElementById('sidebar');
//     if(sidebarEl) sidebarEl.innerHTML = sidebarHtml;

//     // load page fragments into main-content
//     const main = document.getElementById('main-content');
//     const pages = ['dashboard','student','payment','partial','penalty','mailing'];
//     for(const p of pages){
//         const html = await load(`partials/${p}.html`);
//         if(!html) continue;
//         const temp = document.createElement('div');
//         temp.innerHTML = html;
//         while(temp.firstChild) main.appendChild(temp.firstChild);
//     }

//     // showPage function used by sidebar links
//     window.showPage = function(pageId){
//         document.querySelectorAll('.page').forEach(page=>page.classList.remove('active'));
//         const el = document.getElementById(pageId);
//         if(el) el.classList.add('active');
//     };

//     // default page
//     showPage('dashboard');
// });

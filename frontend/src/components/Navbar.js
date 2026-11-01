/**
 * Meramot Global Portal Navigation Component
 */
export function renderNavbar(activePortal = 'home') {
  return `
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
          <div class="flex items-center gap-3">
            <span class="text-2xl font-black text-meramot-700 tracking-tight">মেরামত</span>
            <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-meramot-100 text-meramot-800">CSE470 Edition</span>
          </div>
          <nav class="flex items-center gap-6 text-sm font-medium">
            <a href="/customer" class="${activePortal === 'customer' ? 'text-meramot-700 font-bold' : 'text-slate-600 hover:text-slate-900'}">Customer Hub</a>
            <a href="/vendor" class="${activePortal === 'vendor' ? 'text-meramot-700 font-bold' : 'text-slate-600 hover:text-slate-900'}">Vendor Bidding</a>
            <a href="/rider" class="${activePortal === 'rider' ? 'text-meramot-700 font-bold' : 'text-slate-600 hover:text-slate-900'}">Rider Logistics</a>
            <a href="/admin" class="${activePortal === 'admin' ? 'text-meramot-700 font-bold' : 'text-slate-600 hover:text-slate-900'}">Admin Desk</a>
          </nav>
        </div>
      </div>
    </header>
  `;
}

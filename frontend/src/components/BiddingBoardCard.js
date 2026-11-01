/**
 * Feature R12: Live Bidding Engine & Diagnostic Quote Card
 * Assigned Developer: Farhan Tanvir [22299179]
 */
export function renderBiddingCard(bid) {
  return `
    <div class="card-container border-l-4 border-l-meramot-600 flex flex-col justify-between">
      <div>
        <div class="flex justify-between items-start">
          <h4 class="font-bold text-slate-900 text-lg">${bid.shopName}</h4>
          <span class="text-xs bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full font-semibold border border-emerald-200">
            ★ ${bid.rating || '4.8'}
          </span>
        </div>
        <p class="text-xs text-slate-500 mt-1">${bid.shopAddress || 'Dhaka, Bangladesh'}</p>
        
        <div class="my-4 p-3 bg-slate-50 rounded-lg text-xs space-y-1 text-slate-600">
          <div class="flex justify-between"><span>Estimated Time:</span><span class="font-semibold text-slate-800">${bid.estimatedDays} Days</span></div>
          <div class="flex justify-between"><span>Warranty Period:</span><span class="font-semibold text-slate-800">${bid.warrantyDays} Days</span></div>
          <div class="flex justify-between"><span>Technician Note:</span><span class="italic text-slate-500">${bid.note || 'Genuine parts included'}</span></div>
        </div>
      </div>

      <div class="flex items-center justify-between pt-3 border-t border-slate-100">
        <div>
          <span class="text-xs text-slate-400 block uppercase font-medium">Proposed Quote</span>
          <span class="text-xl font-extrabold text-slate-900">৳${bid.cost}</span>
        </div>
        <button class="btn-primary text-xs px-3 py-1.5" onclick="acceptBid('${bid.id}')">
          Accept Quote
        </button>
      </div>
    </div>
  `;
}

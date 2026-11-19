<style>
.office{display:grid;gap:18px}
.zone{border:1px solid var(--border);border-radius:16px;padding:14px 16px 16px;background:var(--panel)}
.zone>h2{margin:0 0 12px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--text-2)}
.desks{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px}
.desk{position:relative;border:1px solid var(--border);border-radius:14px;padding:14px 14px 12px 18px;background:var(--bg,transparent);overflow:hidden}
.desk::before{content:"";position:absolute;left:0;top:0;bottom:0;width:5px;background:var(--border-2)}
.desk.working::before{background:var(--ok)}.desk.needs::before{background:#d99a12}.desk.problem::before{background:var(--err)}
.desk.off{opacity:.62}
.desk h3{margin:0;font-size:15.5px}.desk .role{margin:2px 0 10px;font-size:12.5px;color:var(--text-2)}
.desk .line{margin:0 0 8px;font-weight:600}.desk ul{margin:0 0 10px;padding:0;list-style:none;font-size:12.5px;color:var(--text-2)}
.desk .lamp{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;margin-bottom:8px}
.desk .lamp i{width:9px;height:9px;border-radius:50%;background:var(--border-2)}
.desk.working .lamp i{background:var(--ok);animation:lamp 1.8s ease-in-out infinite}.desk.needs .lamp i{background:#d99a12}.desk.problem .lamp i{background:var(--err)}
@keyframes lamp{50%{opacity:.35}}
@media (prefers-reduced-motion:reduce){.desk.working .lamp i{animation:none}}
</style>

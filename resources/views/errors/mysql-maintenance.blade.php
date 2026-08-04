<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảo trì hệ thống - Aventura</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
        }
    </style>
</head>
<body class="bg-[#030712] text-zinc-100 min-h-screen flex items-center justify-center p-4 selection:bg-indigo-500/30 selection:text-indigo-200">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(79,70,229,0.08)_0%,transparent_60%)] pointer-events-none"></div>

    <div class="max-w-md w-full text-center relative z-10 bg-slate-950/60 backdrop-blur-xl border border-white/5 p-8 rounded-3xl shadow-2xl">
        <!-- Pulse Indicator -->
        <div class="relative w-20 h-20 mx-auto mb-8 bg-amber-500/10 border border-amber-500/20 rounded-2xl flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-amber-500/5 animate-pulse"></div>
            <!-- Wrench / Hammer SVG -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amber-500 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </div>

        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight bg-gradient-to-r from-zinc-50 via-zinc-200 to-zinc-400 bg-clip-text text-transparent mb-4">
            Bảo trì Cơ sở dữ liệu
        </h1>
        
        <p class="text-zinc-400 text-sm sm:text-base leading-relaxed mb-8">
            {{ $message }}
        </p>

        <div class="space-y-4">
            <a href="/status" class="w-full inline-flex items-center justify-center px-5 py-3 rounded-xl bg-indigo-600 text-white font-semibold text-sm shadow-lg shadow-indigo-600/20 hover:bg-indigo-500 hover:shadow-indigo-500/30 active:scale-[0.98] transition-all cursor-pointer">
                Trang Trạng thái Công khai
            </a>
            
            <div class="text-[11px] text-zinc-600 flex items-center justify-center gap-2">
                <span class="size-1.5 rounded-full bg-amber-500 animate-ping"></span>
                <span>Chúng tôi sẽ sớm trở lại hoạt động bình thường.</span>
            </div>
        </div>
    </div>
</body>
</html>

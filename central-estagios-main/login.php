<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - Portal FSA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6 font-[Inter]">
    <div class="w-full max-w-[400px] bg-white p-10 rounded-[40px] shadow-2xl border border-white">
        <div class="text-center mb-10">
            <div class="w-20 h-20 bg-blue-50 rounded-3xl flex items-center justify-center mx-auto mb-4 text-[#4A9FCA]">
                <i data-lucide="shield-check" class="w-10 h-10"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Portal FSA</h1>
            <p class="text-slate-400">Acesse sua conta</p>
        </div>
        <form action="Dashboard.php" method="POST" class="space-y-5">
            <input type="text" placeholder="CPF" class="w-full p-4 rounded-2xl bg-slate-50 border outline-none focus:ring-2 focus:ring-blue-400">
            <input type="password" placeholder="Senha" class="w-full p-4 rounded-2xl bg-slate-50 border outline-none focus:ring-2 focus:ring-blue-400">
            <button type="submit" class="w-full py-4 bg-[#4A9FCA] text-white font-bold rounded-2xl shadow-lg shadow-blue-100">Entrar</button>
        </form>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>安装 OpenMES</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <img src="/logo_open_mes.png" alt="OpenMES" class="h-20 md:h-24 mx-auto mb-2">
            <p class="text-xl text-gray-600">制造执行系统</p>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">欢迎使用安装向导</h2>

            <div class="space-y-4 mb-8">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-green-600 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-800">安装简单</h3>
                        <p class="text-gray-600 text-sm">只需几步即可运行你的制造系统</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-green-600 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-800">安全配置</h3>
                        <p class="text-gray-600 text-sm">你将创建自己的专用凭据，不使用默认密码！</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-green-600 mt-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-800">几分钟即可完成</h3>
                        <p class="text-gray-600 text-sm">配置数据库、创建管理员账号，即可完成！</p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                <h3 class="font-semibold text-blue-900 mb-2">开始前，请确认已准备好：</h3>
                <ul class="list-disc list-inside text-blue-800 text-sm space-y-1">
                    <li>已创建并可访问的 PostgreSQL 数据库</li>
                    <li>数据库连接信息（主机、端口、库名、用户名、密码）</li>
                    <li>管理员账号的强密码</li>
                </ul>
            </div>

            <div class="flex justify-end">
                <a href="{{ route('install.database') }}" class="btn-touch btn-primary">
                    开始安装
                </a>
            </div>
        </div>

        <div class="text-center mt-6 text-sm text-gray-600">
            <p>开源制造执行系统 v1.0</p>
        </div>
    </div>
</body>
</html>

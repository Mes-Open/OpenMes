<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>安装完成 - OpenMES</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <div class="inline-block p-4 bg-green-100 rounded-full mb-4">
                <svg class="w-16 h-16 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
            <img src="/logo_open_mes.png" alt="OpenMES" class="h-20 md:h-24 mx-auto mb-4">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">安装完成！</h1>
            <p class="text-xl text-gray-600">系统已可使用</p>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">接下来做什么？</h2>

            <div class="space-y-4 mb-8">
                <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-lg">
                    <span class="flex items-center justify-center w-8 h-8 bg-blue-600 text-white rounded-full font-bold">1</span>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">登录系统</h3>
                        <p class="text-gray-600 text-sm">使用刚刚创建的管理员凭据登录</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-lg">
                    <span class="flex items-center justify-center w-8 h-8 bg-blue-600 text-white rounded-full font-bold">2</span>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">创建产线</h3>
                        <p class="text-gray-600 text-sm">设置产线和工位</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-lg">
                    <span class="flex items-center justify-center w-8 h-8 bg-blue-600 text-white rounded-full font-bold">3</span>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">添加用户</h3>
                        <p class="text-gray-600 text-sm">创建操作员和主管账号</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-lg">
                    <span class="flex items-center justify-center w-8 h-8 bg-blue-600 text-white rounded-full font-bold">4</span>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">导入或创建工单</h3>
                        <p class="text-gray-600 text-sm">开始跟踪生产！</p>
                    </div>
                </div>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-8">
                <h3 class="font-semibold text-green-900 mb-2">快速提示：</h3>
                <ul class="list-disc list-inside text-green-800 text-sm space-y-1">
                    <li>应用已使用你的专用凭据保护</li>
                    <li>所有数据存储在 PostgreSQL 数据库中</li>
                    <li>可以在管理后台添加更多用户</li>
                    <li>查看文档了解高级功能</li>
                </ul>
            </div>

            <div class="text-center">
                <a href="{{ route('login') }}" class="inline-block btn-touch btn-primary text-lg px-8">
                    前往登录页
                </a>
            </div>
        </div>

        <div class="text-center mt-6 text-sm text-gray-600">
            <p>需要帮助？请查看文档或访问 GitHub 仓库</p>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>配置 - 安装 OpenMES</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <img src="/logo_open_mes.png" alt="OpenMES" class="h-16 md:h-20 mx-auto mb-2">
            <h1 class="text-2xl font-bold text-gray-800">欢迎使用 OpenMES！</h1>
            <p class="text-gray-600 mt-2">第 1 步，共 3 步：基础配置</p>
        </div>

        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">基础配置</h2>

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('install.environment.setup') }}" x-data="{ loading: false }" @submit="loading = true">
                @csrf

                <div class="mb-4">
                    <label for="app_name" class="form-label">站点名称</label>
                    <input
                        type="text"
                        id="app_name"
                        name="app_name"
                        value="{{ old('app_name', 'OpenMES') }}"
                        class="form-input w-full"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">制造站点的名称</p>
                </div>

                <div class="mb-6">
                    <label for="app_url" class="form-label">站点地址</label>
                    <input
                        type="url"
                        id="app_url"
                        name="app_url"
                        value="{{ old('app_url', 'http://localhost') }}"
                        class="form-input w-full"
                        required
                    >
                    <p class="text-sm text-gray-500 mt-1">访问 OpenMES 的地址</p>
                </div>

                <div class="mb-6">
                    <label for="app_timezone" class="form-label">工厂时区</label>
                    <select id="app_timezone" name="app_timezone" class="form-input w-full" required>
                        @foreach($timezones as $region => $identifiers)
                            <optgroup label="{{ $region }}">
                                @foreach($identifiers as $identifier)
                                    <option value="{{ $identifier }}" @selected(old('app_timezone', $currentTimezone) === $identifier)>
                                        {{ $identifier }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="text-sm text-gray-500 mt-1">
                        用于所有时间戳、报表边界和班次边界。请设置
                        工厂所在时区，而不是服务器时区；否则日报
                        覆盖的时间段会与实际班次不一致。之后可在
                        “设置 → 系统”中修改，无需重新安装。
                    </p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-blue-800 text-sm">
                        <strong>注意：</strong> 这将创建配置文件，并准备进行数据库配置。
                    </p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="btn-touch btn-primary"
                        :disabled="loading"
                        :class="{ 'opacity-50 cursor-not-allowed': loading }"
                    >
                        <span x-show="!loading">继续</span>
                        <span x-show="loading">正在配置...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

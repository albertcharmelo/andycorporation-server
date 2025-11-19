<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Andy Corporación - API Services</title>
    <meta name="description" content="API moderna y escalable para tu plataforma de tecnología. Gestiona productos, inventarios, pedidos y más con la solución integral de Andy Corporación.">
    @vite(['resources/js/home.js'])
</head>
<body class="antialiased">
    <div class="min-h-screen">
        <!-- Hero Section -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
            <!-- Animated background elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-1/2 -right-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl animate-pulse"></div>
                <div class="absolute -bottom-1/2 -left-1/4 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
            </div>

            <!-- Navigation -->
            <nav class="relative z-10 container mx-auto px-4 sm:px-6 py-4 sm:py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2 sm:space-x-3">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-lg flex items-center justify-center text-white font-bold text-lg sm:text-xl">
                            AC
                        </div>
                        <div>
                            <div class="text-white font-semibold text-base sm:text-lg">Andy Corporación</div>
                            <div class="text-cyan-400 text-xs hidden sm:block">API Services</div>
                        </div>
                    </div>
                    <div class="hidden md:flex items-center space-x-6 lg:space-x-8">
                        <a href="#features" class="text-gray-300 hover:text-white transition-colors text-sm lg:text-base">Características</a>
                        <a href="#services" class="text-gray-300 hover:text-white transition-colors text-sm lg:text-base">Servicios</a>
                        <a href="#docs" class="text-gray-300 hover:text-white transition-colors text-sm lg:text-base">Documentación</a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="px-4 lg:px-6 py-2 bg-gradient-to-r from-blue-500 to-cyan-400 text-white rounded-lg hover:shadow-lg hover:shadow-cyan-500/50 transition-all duration-300 text-sm lg:text-base">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="px-4 lg:px-6 py-2 bg-gradient-to-r from-blue-500 to-cyan-400 text-white rounded-lg hover:shadow-lg hover:shadow-cyan-500/50 transition-all duration-300 text-sm lg:text-base">
                                Comenzar
                            </a>
                        @endauth
                    </div>
                    <!-- Mobile menu button -->
                    <button id="mobile-menu-button" class="md:hidden text-white p-2 hover:bg-white/10 rounded-lg transition-colors" aria-label="Toggle menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
                <!-- Mobile menu -->
                <div id="mobile-menu" class="hidden md:hidden mt-4 pb-4 border-t border-white/10">
                    <div class="flex flex-col space-y-3 pt-4">
                        <a href="#features" class="text-gray-300 hover:text-white transition-colors py-2">Características</a>
                        <a href="#services" class="text-gray-300 hover:text-white transition-colors py-2">Servicios</a>
                        <a href="#docs" class="text-gray-300 hover:text-white transition-colors py-2">Documentación</a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-cyan-400 text-white rounded-lg hover:shadow-lg hover:shadow-cyan-500/50 transition-all duration-300 text-center">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-cyan-400 text-white rounded-lg hover:shadow-lg hover:shadow-cyan-500/50 transition-all duration-300 text-center">
                                Comenzar
                            </a>
                        @endauth
                    </div>
                </div>
            </nav>

            <!-- Hero Content -->
            <div class="relative z-10 container mx-auto px-4 sm:px-6 py-12 sm:py-16 md:py-24 lg:py-32">
                <div class="grid md:grid-cols-2 gap-8 md:gap-12 items-center">
                    <div class="space-y-6 sm:space-y-8 animate-fade-in">
                        <div class="inline-block">
                            <span class="px-3 sm:px-4 py-1.5 sm:py-2 bg-cyan-500/10 border border-cyan-500/20 rounded-full text-cyan-400 text-xs sm:text-sm font-medium">
                                API v2.0 Disponible
                            </span>
                        </div>
                        <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-white leading-tight">
                            Potencia tu
                            <span class="block bg-gradient-to-r from-blue-400 to-cyan-400 bg-clip-text text-transparent">
                                E-commerce Tech
                            </span>
                        </h1>
                        <p class="text-base sm:text-lg md:text-xl text-gray-300 leading-relaxed">
                            API moderna y escalable para tu plataforma de tecnología. Gestiona productos, inventarios, pedidos, delivery y más con la solución integral de Andy Corporación.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                            <a href="#docs" class="group px-6 sm:px-8 py-3 sm:py-4 bg-gradient-to-r from-blue-500 to-cyan-400 text-white rounded-lg font-semibold hover:shadow-xl hover:shadow-cyan-500/50 transition-all duration-300 transform hover:-translate-y-1 inline-block text-center text-sm sm:text-base">
                                Explorar API
                                <span class="inline-block ml-2 group-hover:translate-x-1 transition-transform">→</span>
                            </a>
                            <a href="{{ route('login') }}" class="px-6 sm:px-8 py-3 sm:py-4 bg-white/5 backdrop-blur-sm border border-white/10 text-white rounded-lg font-semibold hover:bg-white/10 transition-all duration-300 text-center text-sm sm:text-base">
                                Ver Documentación
                            </a>
                        </div>
                        <div class="flex items-center flex-wrap gap-4 sm:gap-6 md:gap-8 pt-4">
                            <div>
                                <div class="text-2xl sm:text-3xl font-bold text-white">99.9%</div>
                                <div class="text-gray-400 text-xs sm:text-sm">Uptime</div>
                            </div>
                            <div class="h-10 sm:h-12 w-px bg-gray-700"></div>
                            <div>
                                <div class="text-2xl sm:text-3xl font-bold text-white">&lt;100ms</div>
                                <div class="text-gray-400 text-xs sm:text-sm">Respuesta</div>
                            </div>
                            <div class="h-10 sm:h-12 w-px bg-gray-700"></div>
                            <div>
                                <div class="text-2xl sm:text-3xl font-bold text-white">24/7</div>
                                <div class="text-gray-400 text-xs sm:text-sm">Soporte</div>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Visual -->
                    <div class="relative animate-float mt-8 md:mt-0">
                        <div class="relative bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl shadow-2xl border border-white/10 overflow-hidden">
                            <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-cyan-500/5"></div>
                            <div class="relative p-4 sm:p-6 md:p-8">
                                <!-- Code snippet mockup -->
                                <div class="space-y-3 sm:space-y-4">
                                    <div class="flex items-center space-x-2 mb-4 sm:mb-6">
                                        <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-red-500"></div>
                                        <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-yellow-500"></div>
                                        <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-green-500"></div>
                                    </div>
                                    <div class="space-y-2 sm:space-y-3 font-mono text-xs sm:text-sm overflow-x-auto">
                                        <div class="text-cyan-400">// Obtener productos</div>
                                        <div class="text-gray-300 break-words">
                                            <span class="text-purple-400">const</span> response =
                                            <span class="text-blue-400"> await</span> fetch(
                                        </div>
                                        <div class="text-orange-400 pl-2 sm:pl-4 break-all">
                                            '{{ url('/api/products') }}'
                                        </div>
                                        <div class="text-gray-300">);</div>
                                        <div class="h-3 sm:h-4"></div>
                                        <div class="text-cyan-400">// Respuesta JSON</div>
                                        <div class="bg-slate-950/50 rounded p-3 sm:p-4 space-y-1 sm:space-y-2">
                                            <div class="text-gray-400">{</div>
                                            <div class="pl-2 sm:pl-4 text-blue-400">"status": <span class="text-green-400">"success"</span>,</div>
                                            <div class="pl-2 sm:pl-4 text-blue-400">"products": <span class="text-yellow-400">[...]</span>,</div>
                                            <div class="pl-2 sm:pl-4 text-blue-400">"total": <span class="text-orange-400">1247</span></div>
                                            <div class="text-gray-400">}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Floating cards -->
                        <div class="absolute -right-2 sm:-right-4 top-4 sm:top-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-2 sm:p-4 shadow-xl animate-float-delay-1 hidden sm:block">
                            <div class="text-white text-xs font-semibold">API Key</div>
                            <div class="text-blue-100 text-xs mt-1 font-mono">ak_live_***</div>
                        </div>
                        <div class="absolute -left-2 sm:-left-4 bottom-4 sm:bottom-8 bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-lg p-2 sm:p-4 shadow-xl animate-float-delay-2 hidden sm:block">
                            <div class="text-white text-xs font-semibold">Requests</div>
                            <div class="text-cyan-100 text-xl sm:text-2xl font-bold mt-1">2.4M</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div id="features" class="py-12 sm:py-16 md:py-20 lg:py-24 bg-white">
            <div class="container mx-auto px-4 sm:px-6">
                <div class="text-center mb-10 sm:mb-12 md:mb-16">
                    <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 mb-3 sm:mb-4">
                        Diseñado para el
                        <span class="bg-gradient-to-r from-blue-600 to-cyan-500 bg-clip-text text-transparent"> E-commerce Tecnológico</span>
                    </h2>
                    <p class="text-base sm:text-lg md:text-xl text-gray-600 max-w-2xl mx-auto px-4">
                        Endpoints especializados para gestionar tu catálogo de tecnología, desde componentes de PC hasta periféricos gaming.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                    <!-- Feature 1: Productos -->
                    <div class="group bg-white rounded-2xl border border-gray-200 p-6 sm:p-8 hover:shadow-2xl hover:border-blue-500/50 transition-all duration-300 hover:-translate-y-2">
                        <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mb-4 sm:mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2 sm:mb-3">Gestión de Productos</h3>
                        <p class="text-sm sm:text-base text-gray-600 leading-relaxed mb-4">
                            Administra tu catálogo completo: PCs, laptops, componentes, periféricos, sillas gaming y más. Categorización inteligente y búsqueda avanzada.
                        </p>
                        <ul class="space-y-2">
                            <li class="flex items-center text-sm text-gray-600">
                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-2"></span>
                                CRUD completo de productos
                            </li>
                            <li class="flex items-center text-sm text-gray-600">
                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-2"></span>
                                Filtros por categoría y especificaciones
                            </li>
                            <li class="flex items-center text-sm text-gray-600">
                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-2"></span>
                                Sincronización con WooCommerce
                            </li>
                        </ul>
                    </div>

                    <!-- Feature 2: Pedidos -->
                    <div class="group bg-white rounded-2xl border border-gray-200 p-6 sm:p-8 hover:shadow-2xl hover:border-cyan-500/50 transition-all duration-300 hover:-translate-y-2">
                        <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-xl flex items-center justify-center mb-4 sm:mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2 sm:mb-3">Procesamiento de Pedidos</h3>
                        <p class="text-sm sm:text-base text-gray-600 leading-relaxed mb-4">
                            Gestión completa del ciclo de pedidos, desde la creación hasta la entrega. Estados personalizables y tracking en tiempo real.
                        </p>
                        <ul class="space-y-2">
                            <li class="flex items-center text-sm text-gray-600">
                                <span class="w-1.5 h-1.5 bg-cyan-500 rounded-full mr-2"></span>
                                Tracking de pedidos en tiempo real
                            </li>
                            <li class="flex items-center text-sm text-gray-600">
                                <span class="w-1.5 h-1.5 bg-cyan-500 rounded-full mr-2"></span>
                                Estados personalizables
                            </li>
                            <li class="flex items-center text-sm text-gray-600">
                                <span class="w-1.5 h-1.5 bg-cyan-500 rounded-full mr-2"></span>
                                Chat integrado por pedido
                            </li>
                        </ul>
                    </div>

                    <!-- Feature 3: Delivery -->
                    <div class="group bg-white rounded-2xl border border-gray-200 p-6 sm:p-8 hover:shadow-2xl hover:border-blue-500/50 transition-all duration-300 hover:-translate-y-2 sm:col-span-2 lg:col-span-1">
                        <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center mb-4 sm:mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2 sm:mb-3">Sistema de Delivery</h3>
                        <p class="text-sm sm:text-base text-gray-600 leading-relaxed mb-4">
                            Gestión completa de entregas con tracking en tiempo real, actualización de ubicación GPS y sistema SOS para emergencias.
                        </p>
                        <ul class="space-y-2">
                            <li class="flex items-center text-sm text-gray-600">
                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-2"></span>
                                Tracking GPS en tiempo real
                            </li>
                            <li class="flex items-center text-sm text-gray-600">
                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-2"></span>
                                Actualización de estados
                            </li>
                            <li class="flex items-center text-sm text-gray-600">
                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-2"></span>
                                Sistema SOS integrado
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services Section -->
        <div id="services" class="py-12 sm:py-16 md:py-20 lg:py-24 bg-gradient-to-br from-slate-50 to-gray-100">
            <div class="container mx-auto px-4 sm:px-6">
                <div class="grid md:grid-cols-2 gap-8 sm:gap-12 md:gap-16 items-center mb-12 sm:mb-16 md:mb-20 lg:mb-24">
                    <div class="order-2 md:order-1">
                        <div class="bg-white rounded-2xl shadow-xl p-4 sm:p-6 md:p-8 border border-gray-200">
                            <div class="flex items-center justify-between mb-4 sm:mb-6">
                                <span class="text-xs sm:text-sm font-semibold text-gray-500">API ENDPOINT</span>
                                <span class="px-2 sm:px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">GET</span>
                            </div>
                            <div class="bg-slate-900 rounded-lg p-4 sm:p-6 font-mono text-xs sm:text-sm mb-4 overflow-x-auto">
                                <div class="text-gray-400 mb-2">// Búsqueda de productos</div>
                                <div class="text-cyan-400 break-all">{{ url('/api/products') }}</div>
                                <div class="text-gray-400 mt-3 sm:mt-4 text-xs break-all">
                                    ?category=gaming-chairs<br/>
                                    &brand=andy<br/>
                                    &min_price=100<br/>
                                    &stock=available
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="flex-1 h-2 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full animate-pulse"></div>
                                <span class="text-xs text-gray-500">85ms</span>
                            </div>
                        </div>
                    </div>
                    <div class="order-1 md:order-2 space-y-4 sm:space-y-6">
                        <h3 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900">
                            API RESTful
                            <span class="block text-transparent bg-gradient-to-r from-blue-600 to-cyan-500 bg-clip-text">Completa y Potente</span>
                        </h3>
                        <p class="text-base sm:text-lg text-gray-600 leading-relaxed">
                            Endpoints optimizados para cada necesidad de tu e-commerce. Búsquedas avanzadas, filtros personalizados y respuestas rápidas.
                        </p>
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                    <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Paginación Inteligente</div>
                                    <div class="text-gray-600 text-sm">Maneja catálogos grandes con facilidad</div>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-6 h-6 bg-cyan-100 rounded-full flex items-center justify-center flex-shrink-0 mt-1">
                                    <svg class="w-4 h-4 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Filtros Avanzados</div>
                                    <div class="text-gray-600 text-sm">Por categoría, precio, marca, specs técnicas</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-8 sm:gap-12 md:gap-16 items-center">
                    <div class="space-y-4 sm:space-y-6">
                        <h3 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900">
                            Seguridad
                            <span class="block text-transparent bg-gradient-to-r from-blue-600 to-cyan-500 bg-clip-text">de Nivel Empresarial</span>
                        </h3>
                        <p class="text-base sm:text-lg text-gray-600 leading-relaxed">
                            Protege tu negocio y tus clientes con autenticación robusta, encriptación end-to-end y cumplimiento de estándares internacionales.
                        </p>
                        <div class="grid grid-cols-2 gap-3 sm:gap-4">
                            <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-200">
                                <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">256-bit</div>
                                <div class="text-xs sm:text-sm text-gray-600">Encriptación SSL</div>
                            </div>
                            <div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-200">
                                <div class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Sanctum</div>
                                <div class="text-xs sm:text-sm text-gray-600">Autenticación</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-2xl shadow-2xl p-4 sm:p-6 md:p-8 border border-white/10">
                            <div class="flex items-center space-x-2 mb-4 sm:mb-6">
                                <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-red-500"></div>
                                <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-yellow-500"></div>
                                <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-green-500"></div>
                                <span class="ml-2 sm:ml-4 text-gray-400 text-xs sm:text-sm">security.config</span>
                            </div>
                            <div class="space-y-2 sm:space-y-3 font-mono text-xs sm:text-sm overflow-x-auto">
                                <div class="text-gray-400">{</div>
                                <div class="pl-2 sm:pl-4">
                                    <span class="text-blue-400">"authentication"</span>:
                                    <span class="text-orange-400"> "Laravel Sanctum"</span>,
                                </div>
                                <div class="pl-2 sm:pl-4">
                                    <span class="text-blue-400">"encryption"</span>:
                                    <span class="text-orange-400"> "AES-256"</span>,
                                </div>
                                <div class="pl-2 sm:pl-4">
                                    <span class="text-blue-400">"rateLimit"</span>:
                                    <span class="text-orange-400"> 1000</span>,
                                </div>
                                <div class="pl-2 sm:pl-4">
                                    <span class="text-blue-400">"cors"</span>:
                                    <span class="text-green-400"> true</span>,
                                </div>
                                <div class="pl-2 sm:pl-4">
                                    <span class="text-blue-400">"monitoring"</span>:
                                    <span class="text-orange-400"> "24/7"</span>
                                </div>
                                <div class="text-gray-400">}</div>
                            </div>
                            <div class="mt-4 sm:mt-6 flex items-center space-x-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                <span class="text-green-400 text-xs">All systems operational</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="py-12 sm:py-16 md:py-20 lg:py-24 bg-gradient-to-br from-blue-600 via-blue-700 to-cyan-600 relative overflow-hidden">
            <div class="absolute inset-0">
                <div class="absolute top-0 left-1/4 w-48 sm:w-64 h-48 sm:h-64 bg-white/5 rounded-full blur-3xl"></div>
                <div class="absolute bottom-0 right-1/4 w-64 sm:w-96 h-64 sm:h-96 bg-cyan-400/10 rounded-full blur-3xl"></div>
            </div>
            <div class="container mx-auto px-4 sm:px-6 relative z-10">
                <div class="max-w-4xl mx-auto text-center space-y-6 sm:space-y-8">
                    <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold text-white px-4">
                        Comienza a integrar hoy mismo
                    </h2>
                    <p class="text-base sm:text-lg md:text-xl text-blue-100 max-w-2xl mx-auto px-4">
                        Obtén acceso a la API y empieza a potenciar tu e-commerce tecnológico en minutos. Documentación completa y soporte dedicado incluido.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center px-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="px-6 sm:px-8 py-3 sm:py-4 bg-white text-blue-600 rounded-lg font-semibold hover:bg-gray-100 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-2xl inline-block text-sm sm:text-base">
                                Ir al Dashboard
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="px-6 sm:px-8 py-3 sm:py-4 bg-white text-blue-600 rounded-lg font-semibold hover:bg-gray-100 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-2xl inline-block text-sm sm:text-base">
                                Registrarse Gratis
                            </a>
                        @endauth
                        <a href="{{ route('login') }}" class="px-6 sm:px-8 py-3 sm:py-4 bg-white/10 backdrop-blur-sm border-2 border-white/30 text-white rounded-lg font-semibold hover:bg-white/20 transition-all duration-300 inline-block text-sm sm:text-base">
                            Iniciar Sesión
                        </a>
                    </div>
                    <div class="pt-6 sm:pt-8 flex flex-col sm:flex-row items-center justify-center gap-4 sm:gap-6 md:gap-8 text-blue-100 px-4">
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-sm sm:text-base">Sin tarjeta de crédito</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-sm sm:text-base">Configuración en 5 minutos</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-slate-900 text-gray-400 py-8 sm:py-10 md:py-12">
            <div class="container mx-auto px-4 sm:px-6">
                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8 mb-6 sm:mb-8">
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-lg flex items-center justify-center text-white font-bold">
                                AC
                            </div>
                            <div>
                                <div class="text-white font-semibold">Andy Corporación</div>
                                <div class="text-cyan-400 text-xs">API Services</div>
                            </div>
                        </div>
                        <p class="text-sm">
                            Soluciones tecnológicas para e-commerce especializado en componentes de PC, gaming y tecnología.
                        </p>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Producto</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#features" class="hover:text-cyan-400 transition-colors">Características</a></li>
                            <li><a href="#services" class="hover:text-cyan-400 transition-colors">Servicios</a></li>
                            <li><a href="#docs" class="hover:text-cyan-400 transition-colors">Documentación</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Recursos</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="{{ route('login') }}" class="hover:text-cyan-400 transition-colors">Iniciar Sesión</a></li>
                            <li><a href="{{ route('register') }}" class="hover:text-cyan-400 transition-colors">Registrarse</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Empresa</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#" class="hover:text-cyan-400 transition-colors">Nosotros</a></li>
                            <li><a href="#" class="hover:text-cyan-400 transition-colors">Contacto</a></li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                    <p class="text-sm">© {{ date('Y') }} Andy Corporación. Todos los derechos reservados.</p>
                </div>
            </div>
        </footer>
    </div>

    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        @keyframes float-delay-1 {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        @keyframes float-delay-2 {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-25px); }
        }

        @keyframes fade-in {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        .animate-float-delay-1 {
            animation: float-delay-1 4s ease-in-out infinite;
        }

        .animate-float-delay-2 {
            animation: float-delay-2 5s ease-in-out infinite;
        }

        .animate-fade-in {
            animation: fade-in 0.8s ease-out forwards;
        }

        html {
            scroll-behavior: smooth;
        }

        /* Mobile menu toggle */
        #mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        #mobile-menu.show {
            max-height: 500px;
            transition: max-height 0.3s ease-in;
        }

        /* Prevent horizontal scroll */
        body {
            overflow-x: hidden;
        }

        /* Responsive background elements */
        @media (max-width: 640px) {
            .absolute.overflow-hidden > div {
                width: 200px !important;
                height: 200px !important;
            }
        }
    </style>
    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (menuButton && mobileMenu) {
                menuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('show');
                    mobileMenu.classList.toggle('hidden');
                });
            }
        });
    </script>
</body>
</html>


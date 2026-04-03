<!-- Left side column. contains the logo and sidebar -->
<aside class="side-bar tw-relative tw-hidden tw-h-full tw-bg-white tw-w-64 xl:tw-w-64 lg:tw-flex lg:tw-flex-col tw-shrink-0">

    <!-- sidebar: style can be found in sidebar.less -->

    {{-- <a href="{{route('home')}}" class="logo">
		<span class="logo-lg">{{ Session::get('business.name') }}</span>
	</a> --}}

    <a href="{{route('home')}}"
        class="tw-flex tw-items-center tw-justify-center tw-gap-2 tw-w-full tw-border-r tw-h-15 tw-shrink-0 tw-border-primary-500/30" style="background: linear-gradient(135deg, #012d6a 0%, #373435 100%);">
        <img src="{{ asset('img/logo-ghm.png') }}" alt="GrupoHM" class="tw-h-8 tw-w-8 tw-rounded-full tw-bg-white tw-p-0.5 tw-object-contain">
        <p class="tw-text-lg tw-font-medium tw-text-white side-bar-heading tw-text-center">
            GrupoHM <span class="tw-inline-block tw-w-3 tw-h-3 tw-bg-green-400 tw-rounded-full" title="Online"></span>
        </p>
    </a>

    <!-- Sidebar Menu -->
    {!! Menu::render('admin-sidebar-menu', 'adminltecustom') !!}

    <!-- /.sidebar-menu -->
    <!-- /.sidebar -->
</aside>

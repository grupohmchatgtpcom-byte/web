<link href="{{ asset('css/tailwind/app.css?v='.$asset_v) }}" rel="stylesheet">

<link rel="stylesheet" href="{{ asset('css/vendor.css?v='.$asset_v) }}">

@if( in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) )
	<link rel="stylesheet" href="{{ asset('css/rtl.css?v='.$asset_v) }}">
@endif

@yield('css')

<!-- app css -->
<link rel="stylesheet" href="{{ asset('css/app.css?v='.$asset_v) }}">

@if(isset($pos_layout) && $pos_layout)
	<style type="text/css">
		.content{
			padding-bottom: 0px !important;
		}
	</style>
@endif
<style type="text/css">
	/*
	* Pattern lock css
	* Pattern direction
	* http://ignitersworld.com/lab/patternLock.html
	*/
	.patt-wrap {
	  z-index: 10;
	}
	.patt-circ.hovered {
	  background-color: #cde2f2;
	  border: none;
	}
	.patt-circ.hovered .patt-dots {
	  display: none;
	}
	.patt-circ.dir {
	  background-image: url("{{asset('/img/pattern-directionicon-arrow.png')}}");
	  background-position: center;
	  background-repeat: no-repeat;
	}
	.patt-circ.e {
	  -webkit-transform: rotate(0);
	  transform: rotate(0);
	}
	.patt-circ.s-e {
	  -webkit-transform: rotate(45deg);
	  transform: rotate(45deg);
	}
	.patt-circ.s {
	  -webkit-transform: rotate(90deg);
	  transform: rotate(90deg);
	}
	.patt-circ.s-w {
	  -webkit-transform: rotate(135deg);
	  transform: rotate(135deg);
	}
	.patt-circ.w {
	  -webkit-transform: rotate(180deg);
	  transform: rotate(180deg);
	}
	.patt-circ.n-w {
	  -webkit-transform: rotate(225deg);
	   transform: rotate(225deg);
	}
	.patt-circ.n {
	  -webkit-transform: rotate(270deg);
	  transform: rotate(270deg);
	}
	.patt-circ.n-e {
	  -webkit-transform: rotate(315deg);
	  transform: rotate(315deg);
	}
</style>
<!-- GrupoHM Custom Theme (inline to bypass public/css gitignore) -->
<style>
:root {
    --ghm-primary: #0194f3;
    --ghm-dark: #373435;
    --ghm-accent: #012d6a;
    --ghm-primary-light: #33aaff;
    --ghm-primary-dark: #0178c8;
}
.tw-dw-btn-primary, .btn-primary {
    background-color: var(--ghm-primary) !important;
    border-color: var(--ghm-primary) !important;
}
.tw-dw-btn-primary:hover, .btn-primary:hover {
    background-color: var(--ghm-primary-dark) !important;
    border-color: var(--ghm-primary-dark) !important;
}
.pos-express-finalize, #pos-finalize, button[id*="finalize"] {
    background: linear-gradient(135deg, var(--ghm-primary) 0%, var(--ghm-accent) 100%) !important;
    border-color: var(--ghm-accent) !important;
    color: #fff !important;
    font-weight: 700;
}
.pos-express-finalize:hover, #pos-finalize:hover {
    background: linear-gradient(135deg, var(--ghm-accent) 0%, var(--ghm-primary) 100%) !important;
    box-shadow: 0 4px 15px rgba(1, 148, 243, 0.35);
}
.label-primary, .badge-primary {
    background-color: var(--ghm-primary) !important;
}
.nav-tabs > li.active > a,
.nav-tabs > li.active > a:focus,
.nav-tabs > li.active > a:hover {
    border-bottom-color: var(--ghm-primary) !important;
    color: var(--ghm-accent) !important;
}
.progress-bar-primary, .progress-bar {
    background-color: var(--ghm-primary) !important;
}
</style>

@if(!empty($__system_settings['additional_css']))
    {!! $__system_settings['additional_css'] !!}
@endif


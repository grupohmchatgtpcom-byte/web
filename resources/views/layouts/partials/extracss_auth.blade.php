   <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        body {
            background: url('{{ asset("uploads/FONDO DE OFICINA.jpg.jpeg") }}') center/cover no-repeat fixed;
            min-height: 100vh;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, rgba(1,45,106,0.55) 0%, rgba(55,52,53,0.45) 50%, rgba(1,45,106,0.55) 100%);
            z-index: 0;
            pointer-events: none;
        }
        .container-fluid,
        .container-fluid > .row {
            position: relative;
            z-index: 1;
        }

        h1 {
            color: #fff;
        }

        .ghm-login-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(1,148,243,0.2);
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
        }
        .ghm-btn-primary {
            background: linear-gradient(135deg, #0194f3 0%, #012d6a 100%) !important;
            border: none !important;
            transition: all 0.3s ease;
        }
        .ghm-btn-primary:hover {
            background: linear-gradient(135deg, #012d6a 0%, #0194f3 100%) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(1,148,243,0.4);
        }
        .ghm-footer-auth {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            text-align: center;
            margin-top: 2rem;
            text-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }
    </style>

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
            background-image: url("http://pos.test/img/pattern-directionicon-arrow.png");
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
    <style>
        body {
            background: linear-gradient(to right, #6366f1, #3b82f6);
        }

        h1 {
            color: #fff;
        }
    </style>
    <style>
        .action-link[data-v-1552a5b6] {
            cursor: pointer;
        }
    </style>
    <style>
        .action-link[data-v-397d14ca] {
            cursor: pointer;
        }
    </style>
    <style>
        .action-link[data-v-49962cc0] {
            cursor: pointer;
        }
    </style>

<link href="{{ asset('css/tailwind/app.css') }}" rel="stylesheet">

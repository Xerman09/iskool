<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@lang('academics.curriculum_layout')</title>
    <link rel="stylesheet" href="{{url('Modules\Fees\Resources\assets\css\feesStyle.css')}}"/>
    <style>
        .margin_auto{ margin-left: auto; margin-right: 0; }
        .invoice_wrapper{ overflow: auto; }
        .table{ min-width: 600px; }
        @media print {
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        @include('backEnd.academics.partials.curriculumLayoutContent')
    </div>
    <script>
        window.print();
    </script>
</body>
</html>

<?php

return [

    'navigation' => [
        'group' => 'Монитор очередей',
        'label' => 'Монитор очередей',
        'dashboard' => 'Панель',
        'queues' => 'Очереди',
        'jobs' => 'Задания',
        'delayed_jobs' => 'Отложенные задания',
        'completed_jobs' => 'Выполненные задания',
        'failed_jobs' => 'Неудачные задания',
        'queue_details' => 'Детали очереди',
        'failed_job' => 'Неудачное задание',
    ],

    'dashboard' => [
        'title' => 'Монитор очередей',
        'subtitle' => 'Просмотр очередей и активности заданий в реальном времени.',
    ],

    'stats' => [
        'queues' => 'Очереди',
        'pending' => 'В ожидании',
        'processing' => 'В обработке',
        'delayed' => 'Отложено',
        'failed' => 'Неудачные',
        'total' => 'Всего',
        'stuck_jobs' => 'Зависшие задания',
        'delayed_jobs' => 'Отложенные задания',
        'failed_jobs' => 'Неудачные задания',
        'processed_last_hour' => 'Выполнено за час',

        'description' => [
            'total_queues' => 'Всего очередей: :count',
            'processing_count' => ':count в обработке',
            'awaiting_processing' => 'Ожидают обработки',
            'currently_working' => 'Сейчас обрабатываются',
            'scheduled_later' => 'Запланированы позже',
            'failed_count' => 'Неудачные задания',
            'stuck_jobs' => 'Зависли дольше :hours ч (reserved_at)',
            'awaiting_delayed' => 'Задания ожидают обработки',
            'has_failed' => 'Задания завершились с ошибкой',
            'processed_last_hour' => 'Задания выполнены за последний час',
        ],
    ],

    'queues' => [
        'title' => 'Очереди',
        'search_placeholder' => 'Поиск очередей...',
        'empty' => 'Очереди не найдены.',

        'columns' => [
            'queue' => 'Очередь',
            'pending' => 'В ожидании',
            'processing' => 'В обработке',
            'delayed' => 'Отложено',
            'failed' => 'Неудачные',
            'total' => 'Всего',
            'last_activity' => 'Последняя активность',
        ],
    ],

    'queue_details' => [
        'title' => 'Детали очереди: :name',
        'subheading' => 'Статистика выбранной очереди',
        'section_statistics' => 'Статистика',
        'section_details' => 'Детали',
        'last_activity' => 'Последняя активность',
        'view_jobs' => 'Посмотреть задания очереди',
        'not_available' => 'Н/Д',
    ],

    'jobs' => [
        'title' => 'Задания',
        'job_class' => 'Класс задания',
        'pushed_at' => 'Добавлено',
        'available_at' => 'Доступно',
        'delayed_for' => 'Задержка',
        'search_placeholder' => 'Поиск заданий...',
        'empty' => 'Задания не найдены.',
        'unknown' => 'Неизвестно',
    ],

    'delayed_jobs' => [
        'title' => 'Отложенные задания',
    ],

    'completed_jobs' => [
        'title' => 'Выполненные задания',
        'subtitle' => 'Успешно выполненные задания по классам за выбранный период',
        'job' => 'Класс задания',
        'processed' => 'Выполнено',
        'failed' => 'Неудачно',
        'avg_time' => 'Среднее время',
        'max_time' => 'Макс. время',
        'last_activity' => 'Последняя активность',
        'period' => 'Период',
        'search_placeholder' => 'Поиск классов заданий...',
        'empty' => 'За выбранный период выполненных заданий не зафиксировано.',
    ],

    'failed_jobs' => [
        'title' => 'Неудачные задания',
        'payload' => 'Данные задания',
        'exception' => 'Исключение',
        'failed_at' => 'Время ошибки',
        'search_placeholder' => 'Поиск неудачных заданий...',
        'empty' => 'Неудачные задания не найдены.',
    ],

    'failed_job_detail' => [
        'breadcrumb' => 'Монитор очередей / Неудачные задания',
        'back' => 'Назад к неудачным заданиям',
        'job_information' => 'Информация о задании',
        'job_information_subtitle' => 'Данные записи неудачного задания',
        'queue' => 'Очередь',
        'connection' => 'Подключение',
        'payload' => 'Данные задания',
        'payload_subtitle' => 'Исходные данные задания',
        'no_payload' => 'Данные задания отсутствуют.',
        'error' => 'Ошибка',
        'error_subtitle' => 'Исключение, возникшее при выполнении',
        'show_error' => 'Показать ошибку...',
        'unknown' => 'Неизвестно',
    ],

    'actions' => [
        'retry' => 'Повторить',
        'retry_heading' => 'Повторить неудачное задание',
        'retry_description' => 'Вы уверены, что хотите повторить это неудачное задание?',
        'delete' => 'Удалить',
        'delete_heading' => 'Удалить неудачное задание',
        'delete_description' => 'Вы уверены, что хотите удалить это неудачное задание? Это действие необратимо.',
        'retried_title' => 'Задание повторено',
        'retried_body' => 'Неудачное задание #:id отправлено на повтор.',
        'deleted_title' => 'Задание удалено',
        'deleted_body' => 'Неудачное задание #:id удалено.',
    ],

    'filters' => [
        'pushed_at' => 'Добавлено',
        'available_at' => 'Доступно',
        'failed_at' => 'Время ошибки',
        'from_pushed_at' => 'С даты добавления',
        'until_pushed_at' => 'По дату добавления',
        'from_available_at' => 'С даты доступности',
        'until_available_at' => 'По дату доступности',
        'from_failed_at' => 'С даты ошибки',
        'until_failed_at' => 'По дату ошибки',
    ],

    'common' => [
        'id' => 'ID',
        'uuid' => 'UUID',
        'job' => 'Задание',
        'queue' => 'Очередь',
        'status' => 'Статус',
        'attempts' => 'Попытки',
        'connection' => 'Подключение',
        'empty_value' => '—',
    ],

    'status' => [
        'pending' => 'В ожидании',
        'processing' => 'В обработке',
        'unknown' => 'Неизвестно',
    ],

    'delayed' => [
        'minutes' => ':minutes мин|:minutes мин|:minutes мин',
        'hours' => ':hours ч',
    ],

    'activity' => [
        'heading' => 'Активность очередей',
        'description' => 'Текущие задания в каждой очереди — те же значения, что и в статистике выше',
        'running_since' => 'В обработке с',
        'queued' => 'В очереди',
        'reserved_tooltip' => 'Зарезервировано воркером',
        'queued_tooltip' => 'В очереди, готово к запуску',
        'empty_heading' => 'Нет активных заданий',
        'empty_description' => 'Сейчас нет заданий в ожидании или в обработке.',
    ],

    'breakdown' => [
        'heading' => 'Разбивка по заданиям',
        'description' => 'Выполненные и неудачные задания, сгруппированные по очередям',
        'processed' => 'Выполнено',
        'failed' => 'Неудачно',
        'avg_time' => 'Среднее время',
        'max_time' => 'Макс. время',
        'seconds' => ':seconds с',
        'total_processed' => 'Всего выполнено',
        'total_failed' => 'Всего неудачных',
        'empty_heading' => 'Нет активности',
        'empty_description' => 'За выбранный период ничего не выполнялось и не завершалось с ошибкой.',
    ],

    'periods' => [
        'label' => 'Период',
        'hour' => 'Последний час',
        'today' => 'Сегодня',
        '24h' => 'Последние 24 часа',
        '7d' => 'Последние 7 дней',
    ],

    'polling' => [
        'label' => 'Обновление',
        'default' => 'По умолчанию (:seconds с)',
        'default_off' => 'По умолчанию (выкл.)',
        '5s' => '5 секунд',
        '10s' => '10 секунд',
        '30s' => '30 секунд',
        '60s' => '60 секунд',
        'off' => 'Выключено',
    ],

];

<?php

declare(strict_types=1);

namespace Bakery_Credit\Storage;

/** درز خواندن سقف ماهانه — تا سرویس بدون وردپرس تست شود. */
interface AllowanceSource
{
    /** سقف ماهانهٔ کاربر؛ صفر یعنی ادمین هنوز تعریفش نکرده. */
    public function forUser(int $userId): float;
}

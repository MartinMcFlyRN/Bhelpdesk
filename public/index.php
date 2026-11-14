<?php
// Normalmente gestito da nginx (location = / { return 302 /customer/; })
header('Location: /customer/', true, 302);

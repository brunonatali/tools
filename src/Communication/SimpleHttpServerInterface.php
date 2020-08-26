<?php

namespace BrunoNatali\Tools\Communication;

interface SimpleHttpServerInterface 
{

    const SERVER_HTTP_PROC_ORDER_QHS = 0x10; /* Query, header, server params */
    const SERVER_HTTP_PROC_ORDER_QSH = 0x11; /* Query, server params, header */
    const SERVER_HTTP_PROC_ORDER_HQS = 0x12; /* Header, query, server params */
    const SERVER_HTTP_PROC_ORDER_HSQ = 0x13; /* Header, server params, query */
    const SERVER_HTTP_PROC_ORDER_SQH = 0x14; /* Server params, query, header */
    const SERVER_HTTP_PROC_ORDER_SHQ = 0x15; /* Server params, header, query */

    public function start();

}
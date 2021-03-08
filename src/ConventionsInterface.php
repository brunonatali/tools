<?php

namespace BrunoNatali\Tools;

interface ConventionsInterface 
{
    const DATA_APP_ENABLE = 0x1010; /* Tell when desire enable/disable */


    const DATA_TYPE_ACQ = 0X1030; /* Acqusition */
    const DATA_TYPE_CFG = 0X1031; /* Configuration */
    const DATA_TYPE_CURRENT = 0X1032; /* Get / Return current values */
    const DATA_TYPE_ACK = 0X1033; /* Acknowledge */
    const DATA_TYPE_NACK = 0X1034; /* Nonconformity */

    const DATA_TYPE_STATUS = 0x1050; /* Get / Return current status */

    const ACTION_CLEAR_STATUS = 0x2000; /* Clear current status -> OK (0) */ 
    const ACTION_SUBSCRIBE = 0x2001; /* Ask to subscribe */ 
    const ACTION_UNSUBSCRIBE = 0x2002; /* Ask to unsubscribe */ 

}
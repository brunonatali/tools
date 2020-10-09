<?php

namespace BrunoNatali\Tools\Communication;

interface PingInterface 
{
    const ICMP_TYPE_ECHO_REPLY = 0x00;
    const ICMP_TYPE_DESTINATION_UNREACHABLE = 0x03;
    const ICMP_TYPE_REDIRECT = 0x05;
    const ICMP_TYPE_ECHO_REQUEST = 0x08;
    const ICMP_TYPE_TIME_EXCEEDED = 0x11;
    const ICMP_TYPE_PARAMETER_PROBLEM = 0x12;

    const ICMP_CODE_ECHO = 0x00;



}
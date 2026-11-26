import { useEffect, useState } from 'react';

export const FLOWER_STATUS = {
    ARRIVED: { label: '到厂', className: 'bg-om-chip text-om-muted' },
    CURING: { label: '养护中', className: 'bg-om-downtime-bg text-om-downtime' },
    AVAILABLE: { label: '可用', className: 'bg-om-running-bg text-om-running' },
    ALLOCATED: { label: '已付窨', className: 'bg-om-accepted-bg text-om-accepted' },
    EXPIRED: { label: '超窗', className: 'bg-om-blocked-bg text-om-blocked' },
    SCRAPPED: { label: '已报废', className: 'bg-om-chip text-om-muted' },
    DOWNGRADED: { label: '已降级', className: 'bg-om-chip text-om-muted' },
};

export const FLOWER_URGENCY = {
    expired: { text: 'text-om-blocked', bar: 'bg-om-blocked', dot: 'bg-om-blocked' },
    critical: { text: 'text-om-blocked', bar: 'bg-om-blocked', dot: 'bg-om-blocked' },
    warning: { text: 'text-om-downtime', bar: 'bg-om-downtime', dot: 'bg-om-downtime' },
    safe: { text: 'text-om-running', bar: 'bg-om-running', dot: 'bg-om-running' },
};

export function useNow() {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 30000);
        return () => window.clearInterval(timer);
    }, []);

    return now;
}

export function secondsLeft(usableUntil, fallback, now) {
    const end = Date.parse(usableUntil);
    if (Number.isNaN(end)) return Number(fallback ?? 0);
    return Math.floor((end - now) / 1000);
}

export function urgencyFromSeconds(seconds, status) {
    if (['SCRAPPED', 'DOWNGRADED', 'ALLOCATED'].includes(status)) return 'safe';
    if (seconds <= 0 || status === 'EXPIRED') return 'expired';
    if (seconds < 4 * 3600) return 'critical';
    if (seconds < 8 * 3600) return 'warning';
    return 'safe';
}

export function remainingLabel(seconds, status) {
    if (['SCRAPPED', 'DOWNGRADED'].includes(status)) return FLOWER_STATUS[status]?.label ?? '已处置';
    if (status === 'ALLOCATED') return '已付窨';
    if (seconds <= 0) return `超窗 ${duration(Math.abs(seconds))}`;
    return `剩 ${duration(seconds)}`;
}

export function duration(seconds) {
    const totalMinutes = Math.max(0, Math.floor(seconds / 60));
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${String(minutes).padStart(2, '0')}m`;
    return `${minutes}m`;
}

export function clock(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('zh-CN', { hour: '2-digit', minute: '2-digit', hour12: false }).format(date);
}

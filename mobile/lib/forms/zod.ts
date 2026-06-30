import { z } from 'zod';

export const nonEmpty = (label = 'Required') => z.string().trim().min(1, label);
export const email = z.string().trim().email('Invalid email');
export const password = (min = 8) => z.string().min(min, `At least ${min} characters`);
export const optionalString = z.string().trim().optional().default('');

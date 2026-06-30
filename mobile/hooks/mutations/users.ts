import { useMutation, useQueryClient } from '@tanstack/react-query';

import {
  createUser,
  deleteUser,
  resetUserPassword,
  setUserLines,
  updateUser,
  type CreateUserPayload,
  type UpdateUserPayload,
} from '@/api/users';

export function useCreateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateUserPayload) => createUser(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}

export function useUpdateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: UpdateUserPayload }) =>
      updateUser(vars.id, vars.payload),
    onSuccess: (_data, vars) => {
      qc.invalidateQueries({ queryKey: ['users'] });
      qc.invalidateQueries({ queryKey: ['user', vars.id] });
    },
  });
}

export function useDeleteUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteUser(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['users'] }),
  });
}

export function useResetUserPassword() {
  return useMutation({
    mutationFn: (vars: { id: number; password: string; force_password_change?: boolean }) =>
      resetUserPassword(vars.id, {
        password: vars.password,
        force_password_change: vars.force_password_change,
      }),
  });
}

export function useSetUserLines() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; line_ids: number[] }) =>
      setUserLines(vars.id, vars.line_ids),
    onSuccess: (_data, vars) => {
      qc.invalidateQueries({ queryKey: ['user', vars.id] });
      qc.invalidateQueries({ queryKey: ['users'] });
    },
  });
}

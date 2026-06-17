import { useMutation, useQueryClient } from '@tanstack/react-query';

import {
  createCrew,
  createSkill,
  createWageGroup,
  createWorker,
  deleteCrew,
  deleteSkill,
  deleteWageGroup,
  deleteWorker,
  syncWorkerSkills,
  toggleCrewActive,
  toggleWageGroupActive,
  updateCrew,
  updateSkill,
  updateWageGroup,
  updateWorker,
  type CreateCrewPayload,
  type CreateWageGroupPayload,
  type CreateWorkerPayload,
} from '@/api/hr';

const inv = (qc: ReturnType<typeof useQueryClient>, key: string) =>
  qc.invalidateQueries({ queryKey: [key] });

// Skills
export function useCreateSkill() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: createSkill, onSuccess: () => inv(qc, 'skills') });
}
export function useUpdateSkill() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<{ code: string; name: string; description: string }> }) =>
      updateSkill(vars.id, vars.payload),
    onSuccess: (_d, vars) => {
      inv(qc, 'skills');
      qc.invalidateQueries({ queryKey: ['skill', vars.id] });
    },
  });
}
export function useDeleteSkill() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => deleteSkill(id), onSuccess: () => inv(qc, 'skills') });
}

// Wage groups
export function useCreateWageGroup() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateWageGroupPayload) => createWageGroup(payload),
    onSuccess: () => inv(qc, 'wage-groups'),
  });
}
export function useUpdateWageGroup() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<CreateWageGroupPayload> }) =>
      updateWageGroup(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'wage-groups'),
  });
}
export function useDeleteWageGroup() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => deleteWageGroup(id), onSuccess: () => inv(qc, 'wage-groups') });
}
export function useToggleWageGroupActive() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => toggleWageGroupActive(id), onSuccess: () => inv(qc, 'wage-groups') });
}

// Crews
export function useCreateCrew() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (payload: CreateCrewPayload) => createCrew(payload), onSuccess: () => inv(qc, 'crews') });
}
export function useUpdateCrew() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<CreateCrewPayload> }) => updateCrew(vars.id, vars.payload),
    onSuccess: () => inv(qc, 'crews'),
  });
}
export function useDeleteCrew() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => deleteCrew(id), onSuccess: () => inv(qc, 'crews') });
}
export function useToggleCrewActive() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => toggleCrewActive(id), onSuccess: () => inv(qc, 'crews') });
}

// Workers
export function useCreateWorker() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateWorkerPayload) => createWorker(payload),
    onSuccess: () => inv(qc, 'workers'),
  });
}
export function useUpdateWorker() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; payload: Partial<Omit<CreateWorkerPayload, 'skills'>> }) =>
      updateWorker(vars.id, vars.payload),
    onSuccess: (_d, vars) => {
      inv(qc, 'workers');
      qc.invalidateQueries({ queryKey: ['worker', vars.id] });
    },
  });
}
export function useDeleteWorker() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => deleteWorker(id), onSuccess: () => inv(qc, 'workers') });
}
export function useSyncWorkerSkills() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (vars: { id: number; skills: Array<{ id: number; level?: number }> }) =>
      syncWorkerSkills(vars.id, vars.skills),
    onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['worker', vars.id] }),
  });
}

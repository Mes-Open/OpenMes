import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  createWorkerAbsence,
  deleteWorkerAbsence,
  listWorkerAbsences,
} from '@/api/workerAbsences';

export function useWorkerAbsences() {
  return useQuery({ queryKey: ['worker-absences'], queryFn: listWorkerAbsences });
}

export function useCreateWorkerAbsence() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: createWorkerAbsence,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['worker-absences'] }),
  });
}

export function useDeleteWorkerAbsence() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: deleteWorkerAbsence,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['worker-absences'] }),
  });
}

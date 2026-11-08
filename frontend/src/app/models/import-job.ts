export type ImportStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface ImportRowError {
  row: number;
  externalCode: string | null;
  messages: string[];
}

export interface ImportWarning {
  row: number;
  externalCode: string | null;
  message: string;
}

export interface ImportJob {
  id: string;
  status: ImportStatus;
  originalName: string;
  totalRows: number;
  processedRows: number;
  successCount: number;
  errorCount: number;
  progress: number;
  errors: ImportRowError[];
  warnings: ImportWarning[];
  message: string | null;
  createdAt: string;
  startedAt: string | null;
  finishedAt: string | null;
}

export interface ImportJobCreated {
  id: string;
  status: ImportStatus;
}

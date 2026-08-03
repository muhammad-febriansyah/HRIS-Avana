export interface BackupTable {
    name: string;
    rows: number;
}

export interface BackupProps {
    tables: BackupTable[];
    totalTables: number;
    totalRows: number;
    connection: string;
    error: string | null;
}

import React, { useState, useEffect, useCallback, useRef } from 'react';
import axios from 'axios';
import { 
  Search, 
  FileDown, 
  ChevronUp, 
  ChevronDown, 
  Maximize2, 
  Minimize2, 
  Mail, 
  RefreshCcw, 
  Settings,
  MoreVertical,
  ChevronRight,
  Filter,
  FileText,
  User,
  MapPin,
  Calendar,
  Briefcase,
  GraduationCap,
  Award,
  AlertCircle
} from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

function cn(...inputs) {
  return twMerge(clsx(inputs));
}

const DEFAULT_COLUMNS = [
  { id: 'sl', label: 'SL/ID', width: 65, align: 'center', sticky: true },
  { id: 'candidate', label: 'Candidate', width: 220, align: 'left', sticky: true },
  { id: 'location', label: 'Location', width: 80, align: 'left' },
  { id: 'exp', label: 'Exp.', width: 60, align: 'center' },
  { id: 'salary', label: 'Salary', width: 90, align: 'center' },
  { id: 'skills', label: 'Skills', width: 120, align: 'left' },
  { id: 'match', label: 'Match', width: 70, align: 'center' },
  { id: 'reason', label: 'Rating Reason', width: 250, align: 'left' },
  { id: 'added_on', label: 'Added On', width: 100, align: 'center' },
  { id: 'actions', label: 'Actions', width: 80, align: 'center' }
];

export default function CandidateDashboard() {
  const [candidates, setCandidates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [columns, setColumns] = useState(() => {
    const saved = localStorage.getItem('dashboard_columns');
    return saved ? JSON.parse(saved) : DEFAULT_COLUMNS;
  });
  const [expandedAll, setExpandedAll] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [confFilter, setConfFilter] = useState('');
  const [topN, setTopN] = useState('');
  const [pagination, setPagination] = useState({ current_page: 1, total_pages: 1 });

  // Get job details from URL
  const params = new URLSearchParams(window.location.search);
  const jdId = params.get('jd_id') || '';
  const jobTitle = params.get('job_title') || 'Candidate Dashboard';

  const fetchData = useCallback(async (page = 1) => {
    setLoading(true);
    try {
      const response = await axios.get('../api/get_candidates.php', {
        params: {
          jd_id: jdId,
          search,
          shortlisted: statusFilter,
          confirmed: confFilter,
          top: topN,
          page
        }
      });
      setCandidates(response.data.candidates || []);
      setPagination(response.data.pagination || { current_page: 1, total_pages: 1 });
    } catch (error) {
      console.error('Failed to fetch candidates:', error);
    } finally {
      setLoading(false);
    }
  }, [jdId, search, statusFilter, confFilter, topN]);

  useEffect(() => {
    fetchData(1);
  }, [fetchData]);

  const handleResize = (id, newWidth) => {
    const nextCols = columns.map(col => 
      col.id === id ? { ...col, width: Math.max(40, newWidth) } : col
    );
    setColumns(nextCols);
    localStorage.setItem('dashboard_columns', JSON.stringify(nextCols));
  };

  const getStickyOffset = (id) => {
    let offset = 0;
    for (const col of columns) {
      if (col.id === id) break;
      if (col.sticky) offset += col.width;
    }
    return offset;
  };

  return (
    <div className="flex flex-col h-screen bg-slate-50 text-slate-900 overflow-hidden">
      {/* Header Bar */}
      <header className="bg-slate-900 text-white px-6 py-3 flex items-center justify-between shrink-0">
        <div className="flex items-center gap-4">
          <div className="bg-emerald-500/10 p-2 rounded-lg border border-emerald-500/20">
            <Briefcase className="w-5 h-5 text-emerald-400" />
          </div>
          <div className="flex flex-col">
            <h1 className="font-bold text-sm tracking-tight truncate max-w-md">{jobTitle}</h1>
            <div className="flex items-center gap-2 mt-0.5">
              <span className="text-[10px] font-extrabold text-emerald-400 uppercase tracking-widest">Live Sync</span>
              <span className="text-slate-500 text-[10px]">|</span>
              <span className="text-slate-400 text-[10px] font-semibold tracking-wider">#{jdId}</span>
            </div>
          </div>
        </div>
        
        <div className="flex items-center gap-3">
          <button className="bg-slate-800 hover:bg-slate-700 p-2 rounded-lg transition-colors border border-slate-700">
            <RefreshCcw className="w-4 h-4 text-slate-400" />
          </button>
          <div className="h-6 w-px bg-slate-800" />
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center font-bold text-xs">
              JB
            </div>
          </div>
        </div>
      </header>

      {/* Control Bar */}
      <div className="bg-white border-b border-slate-200 px-6 py-2.5 flex items-center gap-3 shrink-0 shadow-sm z-20">
        <div className="relative flex-1 group">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
          <input 
            type="text" 
            placeholder="Search candidates by name, skills, or experience..."
            className="w-full pl-9 pr-4 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        
        <div className="flex items-center gap-2 shrink-0">
          <select 
            className="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
          >
            <option value="">All Status</option>
            <option value="1">Shortlisted</option>
            <option value="0">Not Shortlisted</option>
          </select>
          
          <div className="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5">
            <span className="text-[10px] font-extrabold text-slate-400 uppercase">Top</span>
            <input 
              type="number" 
              placeholder="N"
              className="w-8 bg-transparent text-xs font-bold text-center focus:outline-none"
              value={topN}
              onChange={(e) => setTopN(e.target.value)}
            />
          </div>
        </div>

        <div className="h-6 w-px bg-slate-200 mx-1" />

        <div className="flex items-center gap-2">
          <button className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm transition-all shadow-indigo-200">
            <FileDown className="w-3.5 h-3.5" />
            Export
          </button>
          <button 
            onClick={() => setExpandedAll(!expandedAll)}
            className="flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-600 w-8 h-8 rounded-lg transition-all"
            title={expandedAll ? "Collapse All" : "Expand All"}
          >
            {expandedAll ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4" />}
          </button>
          <button className="bg-slate-100 hover:bg-slate-200 text-slate-600 w-8 h-8 rounded-lg transition-all">
            <Settings className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Main Table */}
      <div className="flex-1 overflow-auto relative scrollbar-stable bg-slate-50">
        <table className={cn(
          "border-separate border-spacing-0 w-max min-w-full",
          expandedAll && "is-expanded"
        )}>
          <thead className="sticky top-0 z-30 shadow-sm">
            <tr>
              {columns.map((col) => (
                <th 
                  key={col.id}
                  className={cn(
                    "bg-slate-50 border-b border-r border-slate-200 px-3 py-2 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-left",
                    col.sticky && "z-40"
                  )}
                  style={{
                    width: col.width,
                    minWidth: col.width,
                    maxWidth: col.width,
                    position: col.sticky ? 'sticky' : 'relative',
                    left: col.sticky ? getStickyOffset(col.id) : undefined,
                    textAlign: col.align
                  }}
                >
                  <div className="flex items-center justify-between gap-1 overflow-hidden">
                    <span className="truncate">{col.label}</span>
                    <Filter className="w-3 h-3 text-slate-300 flex-shrink-0" />
                  </div>
                  <ColumnResizer onResize={(newWidth) => handleResize(col.id, newWidth)} />
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="bg-white">
            {loading ? (
              <LoadingRows columns={columns} />
            ) : candidates.map((candidate, idx) => (
              <tr key={candidate.id} className="group hover:bg-indigo-50/50 transition-colors">
                {columns.map((col) => (
                  <td 
                    key={col.id}
                    className={cn(
                      "border-b border-r border-slate-100 px-3 py-1 text-[11px] text-slate-600 transition-colors",
                      col.sticky && "bg-inherit group-hover:bg-indigo-50/50 z-10",
                      expandedAll ? "align-top pt-2 pb-3" : "align-middle whitespace-nowrap overflow-hidden text-ellipsis"
                    )}
                    style={{
                      width: col.width,
                      minWidth: col.width,
                      maxWidth: col.width,
                      position: col.sticky ? 'sticky' : 'relative',
                      left: col.sticky ? getStickyOffset(col.id) : undefined,
                      textAlign: col.align
                    }}
                  >
                    <CellRenderer 
                      id={col.id} 
                      candidate={candidate} 
                      sl={(pagination.current_page - 1) * 10 + idx + 1} 
                      expanded={expandedAll}
                    />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Pagination Footer */}
      <footer className="bg-white border-t border-slate-200 px-6 py-2.5 flex items-center justify-between shrink-0 shadow-lg z-50">
        <div className="text-[10px] font-medium text-slate-500">
          Showing <span className="text-slate-900 font-bold">{candidates.length}</span> of <span className="text-slate-900 font-bold">{pagination.total_records || '...'}</span> candidates
        </div>
        
        <div className="flex items-center gap-1.5">
          <button 
            disabled={pagination.current_page === 1}
            onClick={() => fetchData(pagination.current_page - 1)}
            className="p-1.5 rounded-md hover:bg-slate-100 disabled:opacity-30 disabled:hover:bg-transparent transition-all"
          >
            <ChevronRight className="w-4 h-4 rotate-180" />
          </button>
          <div className="flex items-center gap-1">
            {Array.from({ length: Math.min(5, pagination.total_pages) }).map((_, i) => (
              <button 
                key={i}
                className={cn(
                  "w-6 h-6 flex items-center justify-center rounded-md text-[10px] font-bold transition-all",
                  pagination.current_page === i + 1 ? "bg-indigo-600 text-white shadow-sm" : "hover:bg-slate-100 text-slate-500"
                )}
                onClick={() => fetchData(i + 1)}
              >
                {i + 1}
              </button>
            ))}
          </div>
          <button 
            disabled={pagination.current_page === pagination.total_pages}
            onClick={() => fetchData(pagination.current_page + 1)}
            className="p-1.5 rounded-md hover:bg-slate-100 disabled:opacity-30 disabled:hover:bg-transparent transition-all"
          >
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
      </footer>
    </div>
  );
}

function ColumnResizer({ onResize }) {
  const isResizing = useRef(false);
  
  const handleMouseDown = (e) => {
    e.preventDefault();
    isResizing.current = true;
    const startX = e.pageX;
    const th = e.target.parentElement;
    const startWidth = th.offsetWidth;

    const handleMouseMove = (moveE) => {
      if (!isResizing.current) return;
      const newWidth = startWidth + (moveE.pageX - startX);
      onResize(newWidth);
    };

    const handleMouseUp = () => {
      isResizing.current = false;
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
      document.body.style.cursor = '';
    };

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
    document.body.style.cursor = 'col-resize';
  };

  return (
    <div 
      className="absolute right-0 top-0 h-full w-1.5 cursor-col-resize hover:bg-indigo-500/40 group-hover:bg-indigo-500/20 transition-colors z-50"
      onMouseDown={handleMouseDown}
    />
  );
}

function CellRenderer({ id, candidate, sl, expanded }) {
  switch (id) {
    case 'sl':
      return (
        <div className="flex flex-col items-center gap-0.5">
          <span className="font-extrabold text-slate-900 text-xs leading-none">{sl}</span>
          <div className="w-3 h-px bg-slate-200" />
          <span className="text-[8px] font-bold text-slate-400 uppercase leading-none">{candidate.id}</span>
        </div>
      );
    case 'candidate':
      return (
        <div className="flex flex-col gap-0.5">
          <div className="flex items-center justify-between">
            <span className="font-bold text-indigo-600 text-[11px] truncate">{candidate.name}</span>
            <a href="#" className="p-0.5 bg-rose-50 text-rose-500 rounded border border-rose-100 hover:bg-rose-500 hover:text-white transition-all shadow-sm">
              <FileText className="w-2.5 h-2.5" />
            </a>
          </div>
          {expanded && (
            <div className="flex flex-col gap-0.5 mt-1 opacity-80">
              <span className="text-[9px] text-slate-500 truncate">{candidate.email_id}</span>
              <span className="text-[9px] text-slate-400 font-medium">{candidate.phone}</span>
              <div className="flex items-center justify-between mt-0.5">
                <span className="text-[9px] text-slate-400 font-bold">{candidate.phone?.split(',').pop()}</span>
                <span className="bg-slate-50 border border-slate-200 px-1 rounded-[3px] text-[7px] font-black uppercase text-slate-500">ID: {candidate.n8n_id}</span>
              </div>
            </div>
          )}
        </div>
      );
    case 'exp':
      return <span className="font-bold text-slate-700">{candidate.total_experience}y</span>;
    case 'salary':
      return <span className="font-bold text-slate-700 whitespace-nowrap">৳{parseFloat(candidate.expected_salary).toLocaleString()}</span>;
    case 'match':
      return (
        <div className="flex items-center justify-center">
          <span className={cn(
            "px-1.5 py-0.5 rounded-full text-[9px] font-black shadow-sm border",
            candidate.match >= 70 ? "bg-emerald-50 text-emerald-600 border-emerald-100" : 
            candidate.match >= 40 ? "bg-amber-50 text-amber-600 border-amber-100" : 
            "bg-slate-50 text-slate-500 border-slate-100"
          )}>
            {candidate.match}%
          </span>
        </div>
      );
    case 'reason':
      return (
        <div className={cn(
          "text-[10px] leading-relaxed text-slate-500",
          !expanded && "truncate"
        )}>
          {candidate.reason_for_rating || '-'}
        </div>
      );
    case 'actions':
      return (
        <div className="flex items-center justify-center gap-1">
          <button className="p-1 hover:bg-indigo-50 rounded transition-colors text-slate-400 hover:text-indigo-600">
            <MoreVertical className="w-3.5 h-3.5" />
          </button>
        </div>
      );
    default:
      return <span className="truncate block">{candidate[id] || '-'}</span>;
  }
}

function LoadingRows({ columns }) {
  return Array.from({ length: 10 }).map((_, i) => (
    <tr key={i} className="animate-pulse">
      {columns.map((col) => (
        <td key={col.id} className="p-3 border-b border-slate-100">
          <div className="h-2.5 bg-slate-100 rounded-full w-full opacity-50" />
        </td>
      ))}
    </tr>
  ));
}
